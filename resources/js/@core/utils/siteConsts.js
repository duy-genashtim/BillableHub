export const SETTING_CATEGORIES = {
  site: 'Site Settings',
  user: 'IVA User Settings',
  report: 'Report Settings',
  'report-time': 'Report Time Settings',
  'report-cat': 'Report Category Settings',
  system: 'System Settings',
  other: 'Other Settings',
}
export const FULL_TIME_DAY_HOURS = 8
export const PART_TIME_DAY_HOURS = 4
export function getPerformanceColor(hours, workStatus) {
  if (!workStatus || workStatus.toLowerCase() === 'no-status') return 'grey'

  const status = workStatus.toLowerCase()
  const targetHours = status === 'part-time' ? PART_TIME_DAY_HOURS : FULL_TIME_DAY_HOURS

  if (hours >= targetHours) return 'success'
  if (hours >= targetHours - 2) return 'warning'
  if (hours > 0) return 'orange'
  return 'error'
}

export function getPerformanceIcon(hours, workStatus) {
  if (!workStatus || workStatus.toLowerCase() === 'no-status') return 'ri-user-unfollow-line'

  const status = workStatus.toLowerCase()
  const targetHours = status === 'part-time' ? PART_TIME_DAY_HOURS : FULL_TIME_DAY_HOURS

  if (hours >= targetHours) return 'ri-checkbox-circle-line'
  if (hours >= targetHours - 2) return 'ri-alert-line'
  if (hours > 0) return 'ri-error-warning-line'
  return 'ri-close-circle-line'
}
