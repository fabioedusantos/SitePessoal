export function useFormatters() {
  const monthYear = (value?: string | null) => {
    if (!value) return 'Atual'
    const [year, month] = value.split('-').map(Number)
    return new Intl.DateTimeFormat('pt-BR', {
      month: 'short',
      year: 'numeric'
    }).format(new Date(year, month - 1, 1))
  }

  const period = (start: string, end?: string | null, current?: boolean | number) =>
    `${monthYear(start)} a ${current ? 'Atual' : monthYear(end)}`

  return { monthYear, period }
}
