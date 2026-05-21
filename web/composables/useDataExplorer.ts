export interface DataTableMeta {
  name: string
  label: string
  row_count: number
  columns: { name: string; type: string }[]
}

export function useDataExplorer() {
  const api = useApi()

  async function tables() {
    const res = await api.get('/data/tables') as { tables?: DataTableMeta[] }
    return res.tables ?? []
  }

  async function rows(table: string, params?: { page?: number; per_page?: number; search?: string; sort?: string; dir?: string }) {
    return api.get(`/data/tables/${table}`, params)
  }

  async function row(table: string, id: string) {
    return api.get(`/data/tables/${table}/${id}`)
  }

  return { tables, rows, row }
}
