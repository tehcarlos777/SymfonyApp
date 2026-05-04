defmodule PhoenixApi.ImportRateLimiter do
  @moduledoc false

  use GenServer

  @config_key __MODULE__

  def start_link(opts) do
    GenServer.start_link(__MODULE__, opts, name: __MODULE__)
  end

  def check_and_track(user_id) do
    GenServer.call(__MODULE__, {:check_and_track, user_id})
  end

  def reset do
    GenServer.call(__MODULE__, :reset)
  end

  @impl true
  def init(_opts) do
    {:ok, %{global: :queue.new(), users: %{}}}
  end

  @impl true
  def handle_call({:check_and_track, user_id}, _from, state) do
    now = now_seconds()
    cfg = config()

    global_queue = prune_expired(state.global, now - cfg.global_window_seconds)
    user_queue = state.users |> Map.get(user_id, :queue.new()) |> prune_expired(now - cfg.user_window_seconds)

    with :ok <- enforce_limit(global_queue, cfg.global_limit, :global, now, cfg.global_window_seconds),
         :ok <- enforce_limit(user_queue, cfg.user_limit, :user, now, cfg.user_window_seconds) do
      updated_global = :queue.in(now, global_queue)
      updated_user = :queue.in(now, user_queue)

      updated_users =
        state.users
        |> Map.put(user_id, updated_user)
        |> drop_empty_user_queues(user_id)

      {:reply, :ok, %{state | global: updated_global, users: updated_users}}
    else
      {:error, _scope, _retry_after} = error ->
        updated_users =
          state.users
          |> Map.put(user_id, user_queue)
          |> drop_empty_user_queues(user_id)

        {:reply, error, %{state | global: global_queue, users: updated_users}}
    end
  end

  @impl true
  def handle_call(:reset, _from, _state) do
    {:reply, :ok, %{global: :queue.new(), users: %{}}}
  end

  defp config do
    Application.get_env(:phoenix_api, @config_key, [])
    |> Enum.into(%{
      user_limit: 5,
      user_window_seconds: 10 * 60,
      global_limit: 1000,
      global_window_seconds: 60 * 60
    })
  end

  defp enforce_limit(queue, limit, scope, now, window_seconds) do
    if :queue.len(queue) < limit do
      :ok
    else
      {{:value, oldest}, _} = :queue.out(queue)
      retry_after = max(oldest + window_seconds - now, 1)
      {:error, scope, retry_after}
    end
  end

  defp prune_expired(queue, min_allowed_timestamp) do
    case :queue.out(queue) do
      {{:value, timestamp}, rest} when timestamp <= min_allowed_timestamp ->
        prune_expired(rest, min_allowed_timestamp)

      _ ->
        queue
    end
  end

  defp drop_empty_user_queues(users, user_id) do
    case Map.get(users, user_id) do
      nil -> users
      queue ->
        if :queue.is_empty(queue) do
          Map.delete(users, user_id)
        else
          users
        end
    end
  end

  defp now_seconds do
    System.monotonic_time(:second)
  end
end
