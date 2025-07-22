// @/@core/utils/worklogHelpers.js
import { WORKLOG_CONFIG } from './worklogConfig'

/**
 * Format hours from decimal to human readable format
 * @param {number} hours - Hours in decimal format
 * @returns {string} Formatted hours string (e.g., "8h 30m")
 */
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

/**
 * Format date for display (timezone-independent)
 * @param {Date|string} date - Date to format
 * @returns {string} Formatted date string
 */
export const formatDate = date => {
  if (!date) return 'N/A'

  try {
    const dateObj = typeof date === 'string' ? new Date(date) : date
    if (isNaN(dateObj.getTime())) return 'N/A'

    return dateObj.toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      timeZone: 'UTC',
    })
  } catch (error) {
    console.warn('Invalid date format:', date)
    return 'N/A'
  }
}

/**
 * Format datetime for display (timezone-independent)
 * @param {Date|string} dateTime - DateTime to format
 * @returns {string} Formatted datetime string
 */
export const formatDateTime = dateTime => {
  if (!dateTime) return ''

  try {
    const dateObj = typeof dateTime === 'string' ? new Date(dateTime) : dateTime
    if (isNaN(dateObj.getTime())) return ''

    return dateObj.toLocaleString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
      timeZone: 'UTC',
    })
  } catch (error) {
    console.warn('Invalid datetime format:', dateTime)
    return ''
  }
}

/**
 * Generate week ranges for a given year
 * Week 1 of 2024 starts on January 15, 2024 (Monday)
 * Each week runs Monday to Sunday
 * @param {number} year - Year to generate weeks for
 * @returns {Array} Array of 52 week objects with start_date, end_date, week_number, year, label
 */
export const getWeekRangeForYear = year => {
  const { START_YEAR, WEEK_1_2024_START, WEEKS_PER_YEAR } = WORKLOG_CONFIG

  if (year < START_YEAR) {
    throw new Error(`Year must be >= ${START_YEAR}`)
  }

  const differentYear = year - START_YEAR + 1
  const totalWeeks = WEEKS_PER_YEAR * differentYear

  const allWeeks = []
  const baseStart = new Date(`${WEEK_1_2024_START}T00:00:00Z`) // force UTC

  var startYear = START_YEAR
  for (let i = 0; i < totalWeeks; i++) {
    const startDate = new Date(baseStart)
    startDate.setUTCDate(baseStart.getUTCDate() + i * 7)

    const endDate = new Date(startDate)
    endDate.setUTCDate(startDate.getUTCDate() + 6)

    const formatDateString = date => date.toISOString().split('T')[0]

    const currentWeekNumber = (i % WEEKS_PER_YEAR) + 1

    allWeeks.push({
      week_number: currentWeekNumber,
      start_date: formatDateString(startDate),
      end_date: formatDateString(endDate),
      year: startYear,
      label: `Week ${currentWeekNumber} (${formatShortDate(startDate)} - ${formatShortDate(endDate)})`,
    })
    if (currentWeekNumber === WEEKS_PER_YEAR) {
      startYear++
    }
  }

  // Filter only weeks that match the target year
  const filteredWeeks = allWeeks.filter(week => week.year === year)
  return filteredWeeks
}

/**
 * Generates grouped 4-week month-like periods with incremental numbering.
 * Each group has a title like "Month 1 (Jan 1 - Jan 28)"
 */
export function getCustomMonthOptionsForSummary(year) {
  const weeks = getWeekRangeForYear(year)
  const monthGroups = []

  for (let i = 0, count = 1; i < weeks.length; i += 4, count++) {
    const monthWeeks = weeks.slice(i, i + 4)

    if (monthWeeks.length === 4) {
      const firstWeek = monthWeeks[0]
      const lastWeek = monthWeeks[3]

      const startDate = new Date(firstWeek.start_date)
      const endDate = new Date(lastWeek.end_date)

      const startStr = startDate.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
      })

      const endStr = endDate.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
      })

      monthGroups.push({
        title: `Month ${count} (${startStr} - ${endStr})`,
        value: count, // Sequential month-like number
        subtitle: `Weeks ${firstWeek.week_number}-${lastWeek.week_number}`,
        weeks: monthWeeks,
        start_date: firstWeek.start_date,
        end_date: lastWeek.end_date,
      })
    }
  }

  return monthGroups
}

/**
 * Returns the current week number based on today's date and the week's range.
 * Falls back to 1 if no match is found or an error occurs.
 */
export function getCurrentWeekNumber() {
  const now = new Date()
  const year = now.getFullYear()

  try {
    const weekRanges = getWeekRangeForYear(year)

    for (let i = 0; i < weekRanges.length; i++) {
      const weekRange = weekRanges[i]
      const start = new Date(weekRange.start_date)
      const end = new Date(weekRange.end_date)

      if (now >= start && now <= end) {
        return weekRange.week_number
      }
    }
  } catch (error) {
    console.warn('Error getting current week:', error)
  }

  return 1 // Default to week 1 if not found
}

/**
 * Format date for short display (e.g., "Jan 15")
 * @param {Date} date - Date to format
 * @returns {string} Short formatted date
 */
export const formatShortDate = date => {
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
  })
}

/**
 * Calculate optimal chart dimensions for horizontal scrolling
 * @param {number} dataLength - Number of data points
 * @param {number} containerWidth - Container width
 * @param {number} minBarWidth - Minimum bar width
 * @param {number} maxBarWidth - Maximum bar width
 * @returns {Object} Chart dimensions and scrolling info
 */
export const calculateChartDimensions = (dataLength, containerWidth = 800, minBarWidth = 60, maxBarWidth = 120) => {
  const padding = 100 // Left and right padding
  const gapBetweenBars = 20
  const availableWidth = containerWidth - padding

  // Calculate ideal bar width based on available space
  const idealBarWidth = Math.max(
    minBarWidth,
    Math.min(maxBarWidth, (availableWidth - (dataLength - 1) * gapBetweenBars) / dataLength),
  )

  // Calculate total required width
  const totalRequiredWidth = idealBarWidth * dataLength + gapBetweenBars * (dataLength - 1) + padding

  const needsHorizontalScroll = totalRequiredWidth > containerWidth
  const chartWidth = needsHorizontalScroll ? totalRequiredWidth : containerWidth

  return {
    chartWidth,
    barWidth: idealBarWidth,
    needsHorizontalScroll,
    totalRequiredWidth,
    containerWidth,
    optimalViewportWidth: Math.min(containerWidth, totalRequiredWidth),
  }
}

/**
 * Get responsive chart settings based on screen size
 * @param {boolean} isMobile - Is mobile device
 * @param {number} dataLength - Number of data points
 * @returns {Object} Responsive chart settings
 */
export const getResponsiveChartSettings = (isMobile, dataLength) => {
  const settings = {
    mobile: {
      containerWidth: 350,
      minBarWidth: 40,
      maxBarWidth: 80,
      chartHeight: 300,
      padding: 60,
      fontSize: 10,
    },
    desktop: {
      containerWidth: 800,
      minBarWidth: 60,
      maxBarWidth: 120,
      chartHeight: 425,
      padding: 80,
      fontSize: 12,
    },
  }

  const config = isMobile ? settings.mobile : settings.desktop
  const dimensions = calculateChartDimensions(dataLength, config.containerWidth, config.minBarWidth, config.maxBarWidth)

  return {
    ...config,
    ...dimensions,
  }
}

/**
 * Create smooth scroll behavior for chart containers
 * @param {HTMLElement} container - Chart container element
 * @param {string} direction - 'left' or 'right'
 * @param {number} distance - Scroll distance in pixels
 */
export const smoothScrollChart = (container, direction = 'right', distance = 200) => {
  if (!container) return

  const currentScroll = container.scrollLeft
  const targetScroll = direction === 'right' ? currentScroll + distance : currentScroll - distance

  container.scrollTo({
    left: Math.max(0, Math.min(targetScroll, container.scrollWidth - container.clientWidth)),
    behavior: 'smooth',
  })
}

/**
 * Add touch/mouse drag scrolling to chart containers
 * @param {HTMLElement} container - Chart container element
 * @returns {Function} Cleanup function
 */
export const enableChartDragScrolling = container => {
  if (!container) return () => {}

  let isDown = false
  let startX
  let scrollLeft

  const handleMouseDown = e => {
    isDown = true
    container.style.cursor = 'grabbing'
    startX = e.pageX - container.offsetLeft
    scrollLeft = container.scrollLeft
  }

  const handleMouseLeave = () => {
    isDown = false
    container.style.cursor = 'grab'
  }

  const handleMouseUp = () => {
    isDown = false
    container.style.cursor = 'grab'
  }

  const handleMouseMove = e => {
    if (!isDown) return
    e.preventDefault()
    const x = e.pageX - container.offsetLeft
    const walk = (x - startX) * 2 // Scroll speed multiplier
    container.scrollLeft = scrollLeft - walk
  }

  // Touch events for mobile
  const handleTouchStart = e => {
    isDown = true
    startX = e.touches[0].pageX - container.offsetLeft
    scrollLeft = container.scrollLeft
  }

  const handleTouchMove = e => {
    if (!isDown) return
    const x = e.touches[0].pageX - container.offsetLeft
    const walk = (x - startX) * 2
    container.scrollLeft = scrollLeft - walk
  }

  const handleTouchEnd = () => {
    isDown = false
  }

  // Add event listeners
  container.addEventListener('mousedown', handleMouseDown)
  container.addEventListener('mouseleave', handleMouseLeave)
  container.addEventListener('mouseup', handleMouseUp)
  container.addEventListener('mousemove', handleMouseMove)
  container.addEventListener('touchstart', handleTouchStart)
  container.addEventListener('touchmove', handleTouchMove)
  container.addEventListener('touchend', handleTouchEnd)

  // Set initial cursor
  container.style.cursor = 'grab'

  // Return cleanup function
  return () => {
    container.removeEventListener('mousedown', handleMouseDown)
    container.removeEventListener('mouseleave', handleMouseLeave)
    container.removeEventListener('mouseup', handleMouseUp)
    container.removeEventListener('mousemove', handleMouseMove)
    container.removeEventListener('touchstart', handleTouchStart)
    container.removeEventListener('touchmove', handleTouchMove)
    container.removeEventListener('touchend', handleTouchEnd)
    container.style.cursor = 'default'
  }
}
