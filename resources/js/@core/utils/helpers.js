// @/@core/utils/helpers.js (Updated with worklog functionality)

// 👉 IsEmpty
export const isEmpty = value => {
  if (value === null || value === undefined || value === '')
    return true
  
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

// 👉 Format Date for Display
export const formatDate = dateString => {
  if (!dateString) return 'N/A'
  
  try {
    const date = new Date(dateString)
    if (isNaN(date.getTime())) return 'N/A'
    
    return date.toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    })
  } catch (error) {
    console.warn('Invalid date format:', dateString)
    return 'N/A'
  }
}

// 👉 Format DateTime for Display
export const formatDateTime = dateTimeString => {
  if (!dateTimeString) return 'N/A'
  
  try {
    const date = new Date(dateTimeString)
    if (isNaN(date.getTime())) return 'N/A'
    
    return date.toLocaleString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false
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
  if (percentage >= 100) return 'EXCELLENT'
  if (percentage >= 90) return 'WARNING'
  return 'POOR'
}

// 👉 Get performance color based on status
export const getPerformanceColor = status => {
  switch (status) {
    case 'EXCELLENT': return 'success'
    case 'WARNING': return 'warning'
    case 'POOR': return 'error'
    default: return 'grey'
  }
}

// 👉 Get performance icon based on status
export const getPerformanceIcon = status => {
  switch (status) {
    case 'EXCELLENT': return 'ri-checkbox-circle-line'
    case 'WARNING': return 'ri-error-warning-line'
    case 'POOR': return 'ri-close-circle-line'
    default: return 'ri-time-line'
  }
}


// 👉 Format date for short display (e.g., "Jan 15")
export const formatShortDate = date => {
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric'
  })
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
    case 1: return 'st'
    case 2: return 'nd'
    case 3: return 'rd'
    default: return 'th'
  }
}

