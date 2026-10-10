export function pretty(iso: string) {
  if (!iso) return '—';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  return date.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

export function shortDay(day: string) {
  if (!day) return '';
  const [year, month, date] = String(day).split('-').map(Number);
  if (!year || !month || !date) return day;
  return new Date(year, month - 1, date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

export function paragraphs(text: string) {
  return String(text || '')
    .split(/\n\s*\n/)
    .map((part) => part.trim())
    .filter(Boolean);
}
