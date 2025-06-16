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

// 👉 IsToday
export const isToday = date => {
  const today = new Date()
  
  return (date.getDate() === today.getDate()
        && date.getMonth() === today.getMonth()
        && date.getFullYear() === today.getFullYear())
}

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

// 👉 Calculate working days between two dates (excluding weekends)
export const getWorkingDays = (startDate, endDate) => {
  const start = typeof startDate === 'string' ? new Date(startDate) : startDate
  const end = typeof endDate === 'string' ? new Date(endDate) : endDate
  
  let workingDays = 0
  const current = new Date(start)
  
  while (current <= end) {
    // Monday = 1, Tuesday = 2, ..., Friday = 5, Saturday = 6, Sunday = 0
    const dayOfWeek = current.getDay()
    if (dayOfWeek !== 0 && dayOfWeek !== 6) { // Not Sunday (0) or Saturday (6)
      workingDays++
    }
    current.setDate(current.getDate() + 1)
  }
  
  return workingDays
}

// 👉 Calculate percentage with safe division
export const calculatePercentage = (numerator, denominator) => {
  if (!denominator || denominator === 0) return 0
  return (numerator / denominator) * 100
}

// 👉 Round number to specified decimal places
export const roundTo = (num, decimals = 2) => {
  return Math.round((num + Number.EPSILON) * Math.pow(10, decimals)) / Math.pow(10, decimals)
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

// 👉 Get month name from month number
export const getMonthName = month => {
  const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ]
  return months[month - 1] || 'Unknown'
}

// 👉 Get short month name from month number
export const getShortMonthName = month => {
  const months = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
  ]
  return months[month - 1] || 'Unknown'
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

// 👉 Debounce function
export const debounce = (func, wait) => {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

// 👉 Throttle function
export const throttle = (func, limit) => {
  let inThrottle
  return function() {
    const args = arguments
    const context = this
    if (!inThrottle) {
      func.apply(context, args)
      inThrottle = true
      setTimeout(() => inThrottle = false, limit)
    }
  }
}

// 👉 Deep clone object
export const deepClone = obj => {
  if (obj === null || typeof obj !== 'object') return obj
  if (obj instanceof Date) return new Date(obj.getTime())
  if (obj instanceof Array) return obj.map(item => deepClone(item))
  if (typeof obj === 'object') {
    const clonedObj = {}
    for (const key in obj) {
      if (obj.hasOwnProperty(key)) {
        clonedObj[key] = deepClone(obj[key])
      }
    }
    return clonedObj
  }
}

// 👉 Generate unique ID
export const generateUniqueId = (prefix = 'id') => {
  return `${prefix}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`
}

// 👉 Capitalize first letter
export const capitalize = str => {
  if (!str) return ''
  return str.charAt(0).toUpperCase() + str.slice(1)
}

// 👉 Convert string to title case
export const toTitleCase = str => {
  if (!str) return ''
  return str.replace(/\w\S*/g, txt => 
    txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()
  )
}

// 👉 Convert string to kebab case
export const toKebabCase = str => {
  if (!str) return ''
  return str
    .replace(/([a-z])([A-Z])/g, '$1-$2')
    .replace(/[\s_]+/g, '-')
    .toLowerCase()
}

// 👉 Convert string to camel case
export const toCamelCase = str => {
  if (!str) return ''
  return str
    .replace(/(?:^\w|[A-Z]|\b\w)/g, (word, index) => 
      index === 0 ? word.toLowerCase() : word.toUpperCase()
    )
    .replace(/\s+/g, '')
}

// 👉 Remove HTML tags from string
export const stripHtml = html => {
  if (!html) return ''
  const tmp = document.createElement('DIV')
  tmp.innerHTML = html
  return tmp.textContent || tmp.innerText || ''
}

// 👉 Truncate string with ellipsis
export const truncate = (str, length = 100, suffix = '...') => {
  if (!str) return ''
  if (str.length <= length) return str
  return str.substring(0, length) + suffix
}

// 👉 Check if string is valid email
export const isValidEmail = email => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

// 👉 Check if string is valid URL
export const isValidUrl = url => {
  try {
    new URL(url)
    return true
  } catch {
    return false
  }
}

// 👉 Get file extension from filename
export const getFileExtension = filename => {
  if (!filename) return ''
  return filename.split('.').pop().toLowerCase()
}

// 👉 Format file size in human readable format
export const formatFileSize = bytes => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

// 👉 Get contrast color (black or white) for a given background color
export const getContrastColor = hexColor => {
  // Remove # if present
  hexColor = hexColor.replace('#', '')
  
  // Convert to RGB
  const r = parseInt(hexColor.substr(0, 2), 16)
  const g = parseInt(hexColor.substr(2, 2), 16)
  const b = parseInt(hexColor.substr(4, 2), 16)
  
  // Calculate luminance
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255
  
  return luminance > 0.5 ? '#000000' : '#FFFFFF'
}

// 👉 Generate random color
export const generateRandomColor = () => {
  return '#' + Math.floor(Math.random() * 16777215).toString(16).padStart(6, '0')
}

// 👉 Scroll to element
export const scrollToElement = (elementId, offset = 0) => {
  const element = document.getElementById(elementId)
  if (element) {
    const elementPosition = element.getBoundingClientRect().top
    const offsetPosition = elementPosition + window.pageYOffset - offset
    
    window.scrollTo({
      top: offsetPosition,
      behavior: 'smooth'
    })
  }
}

// 👉 Copy text to clipboard
export const copyToClipboard = async text => {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch (err) {
    // Fallback for older browsers
    const textArea = document.createElement('textarea')
    textArea.value = text
    document.body.appendChild(textArea)
    textArea.focus()
    textArea.select()
    try {
      document.execCommand('copy')
      document.body.removeChild(textArea)
      return true
    } catch (err) {
      document.body.removeChild(textArea)
      return false
    }
  }
}

// 👉 Download file from URL
export const downloadFile = (url, filename) => {
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

// 👉 Check if device is mobile
export const isMobileDevice = () => {
  return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
}

// 👉 Check if device is iOS
export const isIOS = () => {
  return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream
}

// 👉 Check if device is Android
export const isAndroid = () => {
  return /Android/.test(navigator.userAgent)
}

// 👉 Get browser name
export const getBrowserName = () => {
  const userAgent = navigator.userAgent
  if (userAgent.indexOf('Chrome') > -1) return 'Chrome'
  if (userAgent.indexOf('Firefox') > -1) return 'Firefox'
  if (userAgent.indexOf('Safari') > -1) return 'Safari'
  if (userAgent.indexOf('Edge') > -1) return 'Edge'
  if (userAgent.indexOf('Opera') > -1) return 'Opera'
  return 'Unknown'
}

// 👉 Local storage helpers
export const storage = {
  set: (key, value) => {
    try {
      localStorage.setItem(key, JSON.stringify(value))
      return true
    } catch (error) {
      console.warn('Failed to save to localStorage:', error)
      return false
    }
  },
  
  get: (key, defaultValue = null) => {
    try {
      const item = localStorage.getItem(key)
      return item ? JSON.parse(item) : defaultValue
    } catch (error) {
      console.warn('Failed to get from localStorage:', error)
      return defaultValue
    }
  },
  
  remove: key => {
    try {
      localStorage.removeItem(key)
      return true
    } catch (error) {
      console.warn('Failed to remove from localStorage:', error)
      return false
    }
  },
  
  clear: () => {
    try {
      localStorage.clear()
      return true
    } catch (error) {
      console.warn('Failed to clear localStorage:', error)
      return false
    }
  }
}
