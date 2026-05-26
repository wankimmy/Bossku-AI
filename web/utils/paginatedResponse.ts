/** Laravel paginator JSON shape. */
export type Paginated<T> = {
  data: T[]
  total?: number
  current_page?: number
  per_page?: number
}

export function paginatedItems<T>(payload: T[] | Paginated<T> | null | undefined): T[] {
  if (!payload) return []
  return Array.isArray(payload) ? payload : (payload.data ?? [])
}

export function paginatedTotal<T>(payload: T[] | Paginated<T> | null | undefined): number {
  if (!payload || Array.isArray(payload)) return Array.isArray(payload) ? payload.length : 0
  return payload.total ?? payload.data?.length ?? 0
}
