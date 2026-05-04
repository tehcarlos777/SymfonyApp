defmodule PhoenixApiWeb.PhotoController do
  use PhoenixApiWeb, :controller

  alias PhoenixApi.Repo
  alias PhoenixApi.Media.Photo
  alias PhoenixApi.ImportRateLimiter
  import Ecto.Query

  @photos_index_limit 500

  plug PhoenixApiWeb.Plugs.Authenticate

  def index(conn, _params) do
    current_user = conn.assigns.current_user

    case ImportRateLimiter.check_and_track(current_user.id) do
      :ok ->
        photos =
          Photo
          |> where([p], p.user_id == ^current_user.id)
          |> order_by([p], asc: p.id)
          |> limit(@photos_index_limit)
          |> select([p], %{id: p.id, photo_url: p.photo_url})
          |> Repo.all()

        json(conn, %{photos: photos})

      {:error, :user, retry_after} ->
        conn
        |> put_resp_header("retry-after", Integer.to_string(retry_after))
        |> put_status(:too_many_requests)
        |> json(%{errors: %{detail: "Per-user import rate limit exceeded"}})

      {:error, :global, retry_after} ->
        conn
        |> put_resp_header("retry-after", Integer.to_string(retry_after))
        |> put_status(:too_many_requests)
        |> json(%{errors: %{detail: "Global import rate limit exceeded"}})
    end
  end
end
