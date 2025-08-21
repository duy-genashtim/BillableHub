// @/@core/utils/helpers.js (Updated with worklog functionality)

// 👉 IsEmpty
export const isEmpty = value => {
  if (value === null || value === undefined || value === '') return true

  return !!(Array.isArray(value) && value.length === 0)
}

// 👉 IsNullOrUndefined
export const isNullOrUndefined = value => {
  return value === null || value === undefined
}

// 👉 IsEmptyArray
export const isEmptyArray = arr => {
  return Array.isArray(arr) && arr.length === 0
}

// 👉 IsObject
export const isObject = obj => obj !== null && !!obj && typeof obj === 'object' && !Array.isArray(obj)

// 👉 Format Date for Input (yyyy-MM-dd)
export const formatDateForInput = dateString => {
  if (!dateString) return ''

  try {
    const date = new Date(dateString)
    if (isNaN(date.getTime())) return ''

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
  } catch (error) {
    console.warn('Invalid date format:', dateString)
    return ''
  }
}

// 👉 Format Date for Display (timezone-safe)
export const formatDate = dateString => {
  if (!dateString) return 'N/A'

  try {
    // Extract just the date part to avoid timezone conversion issues
    const dateOnly = dateString.split('T')[0]
    const date = new Date(dateOnly + 'T00:00:00')
    if (isNaN(date.getTime())) return 'N/A'

    return date.toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  } catch (error) {
    console.warn('Invalid date format:', dateString)
    return 'N/A'
  }
}

// 👉 Format Date Only (no timezone conversion)
export const formatDateOnly = dateString => {
  if (!dateString) return ''

  try {
    // Extract just the date part, ignore time/timezone
    const dateOnly = dateString.split('T')[0]
    const date = new Date(dateOnly + 'T00:00:00')
    if (isNaN(date.getTime())) return ''

    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  } catch (error) {
    console.warn('Invalid date format:', dateString)
    return ''
  }
}

// 👉 Format Date Range (timezone-safe)
export const formatDateRangeTimezoneSafe = (startDate, endDate) => {
  if (!startDate && !endDate) return 'No date restrictions'

  const start = startDate ? formatDateOnly(startDate) : 'No start'
  const end = endDate ? formatDateOnly(endDate) : 'No end'

  return `${start} - ${end}`
}

// 👉 Format DateTime for Display (timezone-safe)
export const formatDateTime = dateTimeString => {
  if (!dateTimeString) return 'N/A'

  try {
    // Extract date and time parts to avoid timezone conversion
    const parts = dateTimeString.split('T')
    if (parts.length !== 2) return 'N/A'
    
    const datePart = parts[0] // 2025-08-07
    const timePart = parts[1].split('.')[0] // 09:42:02 (remove milliseconds)
    
    const date = new Date(datePart + 'T' + timePart)
    if (isNaN(date.getTime())) return 'N/A'

    return date.toLocaleString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    })
  } catch (error) {
    console.warn('Invalid datetime format:', dateTimeString)
    return 'N/A'
  }
}

// 👉 Format Hours from decimal to human readable format
export const formatHours = hours => {
  if (!hours || hours === 0) return '0h 0m'

  const wholeHours = Math.floor(hours)
  const minutes = Math.round((hours - wholeHours) * 60)

  if (minutes === 0) {
    return `${wholeHours}h`
  } else if (wholeHours === 0) {
    return `${minutes}m`
  } else {
    return `${wholeHours}h ${minutes}m`
  }
}

export const formatDateRaw = datetimeStr => {
  if (!datetimeStr) return 'N/A'
  const [date] = datetimeStr.split('T')
  const [year, month, day] = date.split('-')
  return `${month}/${day}/${year}`
}

export const formatTimeRaw = datetimeStr => {
  if (!datetimeStr) return 'N/A'
  const [date, time] = datetimeStr.split('T')
  const [hour, minute] = time.split(':')
  return `${hour}:${minute}`
}
// 👉 Format Percentage
export const formatPercentage = percentage => {
  return `${Math.round(percentage || 0)}%`
}

// 👉 Parse JSON safely
export const safeJsonParse = (jsonString, fallback = null) => {
  if (!jsonString) return fallback

  try {
    return JSON.parse(jsonString)
  } catch (error) {
    console.warn('Failed to parse JSON:', jsonString)
    return fallback
  }
}

// 👉 Format number with commas for thousands separator
export const formatNumber = num => {
  return new Intl.NumberFormat('en-US').format(num)
}

// 👉 Get performance status based on percentage
export const getPerformanceStatus = percentage => {
  if (percentage >= 101) return 'EXCEEDED'
  if (percentage >= 99) return 'MEET'
  return 'BELOW'
}
export function getProgressColor(percentage) {
  if (percentage >= 101) return 'success'
  if (percentage >= 99) return 'warning'
  return 'error'
}

// 👉 Get performance color based on status
export const getPerformanceColor = status => {
  switch (status) {
    case 'EXCEEDED':
      return 'success'
    case 'MEET':
      return 'warning'
    case 'BELOW':
      return 'error'
    default:
      return 'grey'
  }
}

// 👉 Get performance icon based on status
export const getPerformanceIcon = status => {
  switch (status) {
    case 'EXCEEDED':
      return 'ri-checkbox-circle-line'
    case 'MEET':
      return 'ri-error-warning-line'
    case 'BELOW':
      return 'ri-close-circle-line'
    default:
      return 'ri-time-line'
  }
}

// 👉 Format date for short display (e.g., "Jan 15")
export const formatShortDate = date => {
  if (!date) return 'N/A'

  const d = typeof date === 'string' ? new Date(date) : date

  if (!(d instanceof Date) || isNaN(d.getTime())) return 'N/A'

  return d.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
  })
}

export function formatDateRange(startDate, endDate) {
  const start = new Date(startDate)
  const end = new Date(endDate)

  const startStr = start.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
  })
  const endStr = end.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    timeZone: 'UTC',
  })

  if (start.getMonth() === end.getMonth()) {
    return `${start.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      timeZone: 'UTC',
    })} - ${end.toLocaleDateString('en-US', { day: 'numeric', year: 'numeric', timeZone: 'UTC' })}`
  } else {
    return `${startStr} - ${endStr}`
  }
}

// 👉 Check if a value is a valid date
export const isValidDate = date => {
  return date instanceof Date && !isNaN(date.getTime())
}

// 👉 Convert hours to minutes
export const hoursToMinutes = hours => {
  return hours * 60
}

// 👉 Convert minutes to hours
export const minutesToHours = minutes => {
  return minutes / 60
}

// 👉 Get ordinal suffix for numbers (1st, 2nd, 3rd, etc.)
export const getOrdinalSuffix = day => {
  if (day > 3 && day < 21) return 'th'
  switch (day % 10) {
    case 1:
      return 'st'
    case 2:
      return 'nd'
    case 3:
      return 'rd'
    default:
      return 'th'
  }
}

// 👉 Format time ago from a given timestamp
export const formatTimeAgo = timestamp => {
  if (!timestamp) return 'Unknown time'

  const now = new Date()
  const past = new Date(timestamp)

  if (isNaN(past.getTime())) return 'Unknown time'

  const seconds = Math.floor((now - past) / 1000)

  const intervals = [
    { label: 'year', seconds: 31536000 },
    { label: 'month', seconds: 2592000 },
    { label: 'day', seconds: 86400 },
    { label: 'hour', seconds: 3600 },
    { label: 'minute', seconds: 60 },
    { label: 'second', seconds: 1 },
  ]

  for (const interval of intervals) {
    const count = Math.floor(seconds / interval.seconds)
    if (count > 0) {
      return `${count} ${interval.label}${count !== 1 ? 's' : ''} ago`
    }
  }

  return 'Just now'
}
