export interface ApiResponse<T> {
  success: boolean
  message: string | null
  data: T
}

export interface PaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface CursorPaginationMeta {
  path: string
  per_page: number
  next_cursor: string | null
  prev_cursor: string | null
  next_page_url: string | null
  prev_page_url: string | null
}

export interface CursorPaginatedResponse<T> {
  success: boolean
  message: string | null
  data: T[]
  meta: CursorPaginationMeta
}

export interface PaginatedResponse<T> {
  success: boolean
  message: string | null
  data: T[]
  meta: PaginationMeta
}

export interface ListParams {
  page?: number
  per_page?: number
  search?: string
}
