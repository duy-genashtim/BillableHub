// @/@core/utils/dateValidation.js
import { getCurrentWeekNumber, getWeekRangeForYear } from './worklogHelpers'

/**
 * Get the maximum selectable date (today) in YYYY-MM-DD format
 * @returns {string} Today's date in YYYY-MM-DD format
 */
export const getMaxSelectableDate = () => {
  const today = new Date()
  return today.toISOString().split('T')[0]
}

/**
 * Get the maximum selectable week number for a given year
 * @param {number} year - The year to check
 * @returns {number} Maximum week number (current week if current year, otherwise 52)
 */
export const getMaxSelectableWeek = (year) => {
  const currentYear = new Date().getFullYear()

  if (year === currentYear) {
    return getCurrentWeekNumber()
  }

  // For past years, allow all weeks
  return year < currentYear ? 52 : 0
}

/**
 * Get the maximum selectable month for a given year
 * @param {number} year - The year to check
 * @returns {number} Maximum month number (current month if current year, otherwise 12)
 */
export const getMaxSelectableMonth = (year) => {
  const currentYear = new Date().getFullYear()
  const currentMonth = new Date().getMonth() + 1 // getMonth() returns 0-11

  if (year === currentYear) {
    return currentMonth
  }

  // For past years, allow all months
  return year < currentYear ? 12 : 0
}

/**
 * Filter week options to exclude future weeks
 * @param {Array} weekOptions - Array of week objects with week_number property
 * @param {number} year - The year being viewed
 * @param {boolean} isYearlyReport - Whether this is for a yearly report (skip filtering if true)
 * @returns {Array} Filtered week options excluding future weeks
 */
export const filterFutureWeeks = (weekOptions, year, isYearlyReport = false) => {
  // For yearly reports, return all weeks without filtering
  if (isYearlyReport) {
    return weekOptions
  }

  const maxWeek = getMaxSelectableWeek(year)

  if (maxWeek === 0) {
    return [] // No weeks allowed for future years
  }

  return weekOptions.filter(week => week.week_number <= maxWeek)
}

/**
 * Filter month options to exclude future months
 * @param {Array} monthOptions - Array of month objects with value property
 * @param {number} year - The year being viewed
 * @param {boolean} isYearlyReport - Whether this is for a yearly report (skip filtering if true)
 * @returns {Array} Filtered month options excluding future months
 */
export const filterFutureMonths = (monthOptions, year, isYearlyReport = false) => {
  // For yearly reports, return all months without filtering
  if (isYearlyReport) {
    return monthOptions
  }

  const maxMonth = getMaxSelectableMonth(year)

  if (maxMonth === 0) {
    return [] // No months allowed for future years
  }

  return monthOptions.filter(month => month.value <= maxMonth)
}

/**
 * Check if a date is allowed (not in the future)
 * @param {string|Date} date - Date to check
 * @returns {boolean} True if date is today or in the past
 */
export const isDateAllowed = (date) => {
  const checkDate = new Date(date)
  const today = new Date()

  // Set time to start of day for accurate comparison
  checkDate.setHours(0, 0, 0, 0)
  today.setHours(0, 0, 0, 0)

  return checkDate <= today
}

/**
 * Check if a week selection is allowed
 * @param {number} year - The year
 * @param {number} weekNumber - The week number
 * @returns {boolean} True if week is allowed
 */
export const isWeekAllowed = (year, weekNumber) => {
  const maxWeek = getMaxSelectableWeek(year)
  return weekNumber <= maxWeek
}

/**
 * Check if a month selection is allowed
 * @param {number} year - The year
 * @param {number} month - The month (1-12)
 * @returns {boolean} True if month is allowed
 */
export const isMonthAllowed = (year, month) => {
  const maxMonth = getMaxSelectableMonth(year)
  return month <= maxMonth
}

/**
 * Get available week options for a year, filtered by date restrictions
 * @param {number} year - The year to get weeks for
 * @returns {Array} Array of available week objects
 */
export const getAvailableWeeksForYear = (year) => {
  const allWeeks = getWeekRangeForYear(year)
  return filterFutureWeeks(allWeeks, year)
}