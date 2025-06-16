// @/@core/utils/worklogHelpers.js
import { WORKLOG_CONFIG } from './worklogConfig';
/**
 * Format hours from decimal to human readable format
 * @param {number} hours - Hours in decimal format
 * @returns {string} Formatted hours string (e.g., "8h 30m")
 */
export const formatHours = (hours) => {
  if (!hours || hours === 0) return '0h 0m';

  const wholeHours = Math.floor(hours);
  const minutes = Math.round((hours - wholeHours) * 60);

  if (minutes === 0) {
    return `${wholeHours}h`;
  } else if (wholeHours === 0) {
    return `${minutes}m`;
  } else {
    return `${wholeHours}h ${minutes}m`;
  }
};

/**
 * Format percentage value
 * @param {number} percentage - Percentage value
 * @returns {string} Formatted percentage (e.g., "85%")
 */
export const formatPercentage = (percentage) => {
  return `${Math.round(percentage || 0)}%`;
};

/**
 * Format date for display (timezone-independent)
 * @param {Date|string} date - Date to format
 * @returns {string} Formatted date string
 */
export const formatDate = (date) => {
  if (!date) return 'N/A';
 
  try {
    const dateObj = typeof date === 'string' ? new Date(date) : date;
    if (isNaN(dateObj.getTime())) return 'N/A';
   
    return dateObj.toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      timeZone: 'UTC'
    });
  } catch (error) {
    console.warn('Invalid date format:', date);
    return 'N/A';
  }
};

/**
 * Format datetime for display (timezone-independent)
 * @param {Date|string} dateTime - DateTime to format
 * @returns {string} Formatted datetime string
 */
export const formatDateTime = (dateTime) => {
  if (!dateTime) return '';
 
  try {
    const dateObj = typeof dateTime === 'string' ? new Date(dateTime) : dateTime;
    if (isNaN(dateObj.getTime())) return '';
   
    return dateObj.toLocaleString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
      timeZone: 'UTC'
    });
  } catch (error) {
    console.warn('Invalid datetime format:', dateTime);
    return '';
  }
};
/**
 * Format date for display
 * @param {Date|string} date - Date to format
 * @returns {string} Formatted date string
 */
// export const formatDate = (date) => {
//   if (!date) return 'N/A';
  
//   try {
//     const dateObj = typeof date === 'string' ? new Date(date) : date;
//     if (isNaN(dateObj.getTime())) return 'N/A';
    
//     return dateObj.toLocaleDateString('en-US', {
//       weekday: 'short',
//       month: 'short',
//       day: 'numeric',
//       year: 'numeric'
//     });
//   } catch (error) {
//     console.warn('Invalid date format:', date);
//     return 'N/A';
//   }
// };

// /**
//  * Format datetime for display
//  * @param {Date|string} dateTime - DateTime to format
//  * @returns {string} Formatted datetime string
//  */
// export const formatDateTime = (dateTime) => {
//   if (!dateTime) return '';
  
//   try {
//     const dateObj = typeof dateTime === 'string' ? new Date(dateTime) : dateTime;
//     if (isNaN(dateObj.getTime())) return '';
    
//     return dateObj.toLocaleString('en-US', {
//       weekday: 'short',
//       month: 'short',
//       day: 'numeric',
//       hour: '2-digit',
//       minute: '2-digit',
//       hour12: false
//     });
//   } catch (error) {
//     console.warn('Invalid datetime format:', dateTime);
//     return '';
//   }
// };

/**
 * Get week number for a given date based on our custom week calculation
 * Week 1 of 2024 starts on January 15, 2024 (Monday)
 * Each week runs Monday to Sunday
 * @param {Date} date - Date to get week number for
 * @returns {number} Week number
 */
export const getWeekNumber = (date) => {
  const year = date.getFullYear();
  const weekRanges = getWeekRangeForYear(year);
  
  // Convert date to YYYY-MM-DD format for comparison
  const dateString = date.toISOString().split('T')[0];
  
  for (let i = 0; i < weekRanges.length; i++) {
    const weekRange = weekRanges[i];
    
    // Compare date strings directly
    if (dateString >= weekRange.start_date && dateString <= weekRange.end_date) {
      return weekRange.week_number;
    }
  }
  
  // If date is before week 1 of the year, it belongs to previous year
  if (dateString < weekRanges[0].start_date) {
    // Check last week of previous year
    const prevYearWeeks = getWeekRangeForYear(year - 1);
    for (let i = prevYearWeeks.length - 1; i >= 0; i--) {
      const weekRange = prevYearWeeks[i];
      if (dateString >= weekRange.start_date && dateString <= weekRange.end_date) {
        return weekRange.week_number;
      }
    }
  }
  
  // If date is after last week of the year, it belongs to next year
  if (dateString > weekRanges[weekRanges.length - 1].end_date) {
    // Check first weeks of next year
    const nextYearWeeks = getWeekRangeForYear(year + 1);
    for (let i = 0; i < Math.min(4, nextYearWeeks.length); i++) {
      const weekRange = nextYearWeeks[i];
      if (dateString >= weekRange.start_date && dateString <= weekRange.end_date) {
        return weekRange.week_number;
      }
    }
  }
  
  return 1; // Default to week 1 if not found
};

/**
 * Generate week ranges for a given year
 * Week 1 of 2024 starts on January 15, 2024 (Monday)
 * Each week runs Monday to Sunday
 * @param {number} year - Year to generate weeks for
 * @returns {Array} Array of 52 week objects with start_date, end_date, week_number, year, label
 */
export const getWeekRangeForYear = (year) => {
  const { START_YEAR, WEEK_1_2024_START, WEEKS_PER_YEAR } = WORKLOG_CONFIG;

  if (year < START_YEAR) {
    throw new Error(`Year must be >= ${START_YEAR}`);
  }

  const differentYear = year - START_YEAR + 1;
  const totalWeeks = WEEKS_PER_YEAR * differentYear;

  const allWeeks = [];
  const baseStart = new Date(`${WEEK_1_2024_START}T00:00:00Z`); // force UTC

  var startYear = START_YEAR;
  for (let i = 0; i < totalWeeks; i++) {
    const startDate = new Date(baseStart);
    startDate.setUTCDate(baseStart.getUTCDate() + i * 7);

    const endDate = new Date(startDate);
    endDate.setUTCDate(startDate.getUTCDate() + 6);

    const formatDateString = (date) => date.toISOString().split('T')[0];

    // const startYear = startDate.getUTCFullYear();
    const currentWeekNumber = (i % WEEKS_PER_YEAR) + 1;

    allWeeks.push({
      week_number: currentWeekNumber,
      start_date: formatDateString(startDate),
      end_date: formatDateString(endDate),
      year: startYear,
      label: `Week ${currentWeekNumber} (${formatShortDate(startDate)} - ${formatShortDate(endDate)})`
    });
    if (currentWeekNumber === WEEKS_PER_YEAR) {
      startYear++;
    }
  }

  // Filter only weeks that match the target year
  const filteredWeeks = allWeeks.filter(week => week.year === year);
  return filteredWeeks;
};

/**
 * Generate week ranges for a given year
 * Week 1 of 2024 starts on January 15, 2024 (Monday)
 * Each week runs Monday to Sunday
 * @param {number} year - Year to generate weeks for
 * @returns {Array} Array of week objects with start_date, end_date, week_number
 */
// export const getWeekRangeForYear = (year) => {
//   const weeks = [];
  
//   // Base reference: Week 1 of 2024 starts on January 15, 2024 (Monday)
//   const baseYear = 2024;
//   const baseWeek1Start = new Date(2024, 0, 15); // January 15, 2024 (Monday)
  
//   // Ensure the base date is actually a Monday
//   if (baseWeek1Start.getDay() !== 1) {
//     console.warn('Base week 1 start date is not a Monday');
//   }
//   console.log(baseWeek1Start);
  
//   // Calculate the start of week 1 for the requested year
//   let week1Start;
//   if (year === baseYear) {
//     week1Start = new Date(baseWeek1Start);
//   } else {
//     // For other years, find the Monday closest to January 15
//     const jan15 = new Date(year, 0, 15);
//     const dayOfWeek = jan15.getDay(); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
    
//     let mondayOffset;
//     if (dayOfWeek === 0) {
//       // If Jan 15 is Sunday, go back 6 days to get Monday
//       mondayOffset = -6;
//     } else if (dayOfWeek === 1) {
//       // If Jan 15 is Monday, use it as is
//       mondayOffset = 0;
//     } else {
//       // If Jan 15 is Tuesday-Saturday, go back to get the Monday
//       mondayOffset = 1 - dayOfWeek;
//     }
    
//     week1Start = new Date(jan15);
//     week1Start.setDate(jan15.getDate() + mondayOffset);
//   }
  
//   // Generate 52 weeks for the year
//   for (let weekNum = 1; weekNum <= 52; weekNum++) {
//     const startDate = new Date(week1Start);
//     startDate.setDate(week1Start.getDate() + (weekNum - 1) * 7);
    
//     // End date is 6 days after start date (Sunday)
//     const endDate = new Date(startDate);
//     endDate.setDate(startDate.getDate() + 6);
    
//     // Verify that start is Monday (1) and end is Sunday (0)
//     if (startDate.getDay() !== 1) {
//       console.warn(`Week ${weekNum} of ${year} does not start on Monday`);
//     }
//     if (endDate.getDay() !== 0) {
//       console.warn(`Week ${weekNum} of ${year} does not end on Sunday`);
//     }
    
//     // Format dates as YYYY-MM-DD
//     const formatDateString = (date) => {
//       return date.toISOString().split('T')[0];
//     };
    
//     weeks.push({
//       week_number: weekNum,
//       start_date: formatDateString(startDate),
//       end_date: formatDateString(endDate),
//       year: year,
//       label: `Week ${weekNum} (${formatShortDate(startDate)} - ${formatShortDate(endDate)})`
//     });
//   }
  
//   return weeks;
// };

/**
 * Format date for short display (e.g., "Jan 15")
 * @param {Date} date - Date to format
 * @returns {string} Short formatted date
 */
export const formatShortDate = (date) => {
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
     timeZone: 'UTC'
  });
};

/**
 * Get date range for monthly selection
 * @param {number} year - Year
 * @param {number} month - Month (1-12)
 * @returns {Object} Object with start_date and end_date
 */
export const getMonthlyDateRange = (year, month) => {
  const startDate = new Date(year, month - 1, 1);
  const endDate = new Date(year, month, 0); // Last day of the month
  
  return {
    start_date: startDate.toISOString().split('T')[0],
    end_date: endDate.toISOString().split('T')[0]
  };
};

/**
 * Get date range for bimonthly selection
 * @param {number} year - Year
 * @param {number} month - Month (1-12)
 * @param {number} splitDate - Date that separates first and second half
 * @returns {Object} Object with first_half and second_half date ranges
 */
export const getBimonthlyDateRange = (year, month, splitDate = 15) => {
  const firstHalfStart = new Date(year, month - 1, 1);
  const firstHalfEnd = new Date(year, month - 1, splitDate);
  
  const secondHalfStart = new Date(year, month - 1, splitDate + 1);
  const secondHalfEnd = new Date(year, month, 0); // Last day of the month
  
  return {
    first_half: {
      start_date: firstHalfStart.toISOString().split('T')[0],
      end_date: firstHalfEnd.toISOString().split('T')[0]
    },
    second_half: {
      start_date: secondHalfStart.toISOString().split('T')[0],
      end_date: secondHalfEnd.toISOString().split('T')[0]
    }
  };
};

/**
 * Calculate working days between two dates (excluding weekends)
 * @param {string|Date} startDate - Start date
 * @param {string|Date} endDate - End date
 * @returns {number} Number of working days
 */
export const getWorkingDays = (startDate, endDate) => {
  const start = typeof startDate === 'string' ? new Date(startDate) : startDate;
  const end = typeof endDate === 'string' ? new Date(endDate) : endDate;
  
  let workingDays = 0;
  const current = new Date(start);
  
  while (current <= end) {
    // Monday = 1, Tuesday = 2, ..., Friday = 5, Saturday = 6, Sunday = 0
    const dayOfWeek = current.getDay();
    if (dayOfWeek !== 0 && dayOfWeek !== 6) { // Not Sunday (0) or Saturday (6)
      workingDays++;
    }
    current.setDate(current.getDate() + 1);
  }
  
  return workingDays;
};

/**
 * Get performance status based on percentage
 * @param {number} percentage - Performance percentage
 * @returns {string} Performance status (EXCELLENT, WARNING, POOR)
 */
export const getPerformanceStatus = (percentage) => {
  if (percentage >= 100) return 'EXCELLENT';
  if (percentage >= 90) return 'WARNING';
  return 'POOR';
};

/**
 * Get performance color based on status
 * @param {string} status - Performance status
 * @returns {string} Color name
 */
export const getPerformanceColor = (status) => {
  switch (status) {
    case 'EXCELLENT': return 'success';
    case 'WARNING': return 'warning';
    case 'POOR': return 'error';
    default: return 'grey';
  }
};

/**
 * Get performance icon based on status
 * @param {string} status - Performance status
 * @returns {string} Icon name
 */
export const getPerformanceIcon = (status) => {
  switch (status) {
    case 'EXCELLENT': return 'ri-checkbox-circle-line';
    case 'WARNING': return 'ri-error-warning-line';
    case 'POOR': return 'ri-close-circle-line';
    default: return 'ri-time-line';
  }
};

/**
 * Parse JSON safely with fallback
 * @param {string} jsonString - JSON string to parse
 * @param {*} fallback - Fallback value if parsing fails
 * @returns {*} Parsed object or fallback
 */
export const safeJsonParse = (jsonString, fallback = null) => {
  if (!jsonString) return fallback;
  
  try {
    return JSON.parse(jsonString);
  } catch (error) {
    console.warn('Failed to parse JSON:', jsonString);
    return fallback;
  }
};

/**
 * Check if a date is today
 * @param {Date|string} date - Date to check
 * @returns {boolean} True if date is today
 */
export const isToday = (date) => {
  const today = new Date();
  const checkDate = typeof date === 'string' ? new Date(date) : date;
  
  return (checkDate.getDate() === today.getDate() &&
          checkDate.getMonth() === today.getMonth() &&
          checkDate.getFullYear() === today.getFullYear());
};

/**
 * Get current week info based on our custom week calculation
 * Uses Monday-Sunday week format
 * @returns {Object} Current week information
 */
export const getCurrentWeekInfo = () => {
  const now = new Date();
  const year = now.getFullYear();
  const weekNumber = getWeekNumber(now);
  const weekRanges = getWeekRangeForYear(year);
  
  const currentWeek = weekRanges.find(week => week.week_number === weekNumber);
  if (currentWeek) {
    return {
      ...currentWeek,
      is_current: true
    };
  }
  
  // If not found in current year, check adjacent years
  if (weekNumber === 1) {
    // Might be in next year
    const nextYearWeeks = getWeekRangeForYear(year + 1);
    const nextYearWeek = nextYearWeeks.find(week => week.week_number === weekNumber);
    if (nextYearWeek) {
      const dateString = now.toISOString().split('T')[0];
      if (dateString >= nextYearWeek.start_date && dateString <= nextYearWeek.end_date) {
        return {
          ...nextYearWeek,
          is_current: true
        };
      }
    }
  }
  
  if (weekNumber >= 50) {
    // Might be in previous year's last weeks
    const prevYearWeeks = getWeekRangeForYear(year - 1);
    const prevYearWeek = prevYearWeeks.find(week => week.week_number === weekNumber);
    if (prevYearWeek) {
      const dateString = now.toISOString().split('T')[0];
      if (dateString >= prevYearWeek.start_date && dateString <= prevYearWeek.end_date) {
        return {
          ...prevYearWeek,
          is_current: true
        };
      }
    }
  }
  
  // Fallback to first week of current year
  return {
    ...weekRanges[0],
    is_current: false
  };
};

/**
 * Calculate percentage with safe division
 * @param {number} numerator - Numerator
 * @param {number} denominator - Denominator
 * @returns {number} Percentage (0 if denominator is 0)
 */
export const calculatePercentage = (numerator, denominator) => {
  if (!denominator || denominator === 0) return 0;
  return (numerator / denominator) * 100;
};

/**
 * Round number to specified decimal places
 * @param {number} num - Number to round
 * @param {number} decimals - Number of decimal places (default: 2)
 * @returns {number} Rounded number
 */
export const roundTo = (num, decimals = 2) => {
  return Math.round((num + Number.EPSILON) * Math.pow(10, decimals)) / Math.pow(10, decimals);
};

/**
 * Format number with commas for thousands separator
 * @param {number} num - Number to format
 * @returns {string} Formatted number string
 */
export const formatNumber = (num) => {
  return new Intl.NumberFormat('en-US').format(num);
};

/**
 * Get month name from month number
 * @param {number} month - Month number (1-12)
 * @returns {string} Month name
 */
export const getMonthName = (month) => {
  const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ];
  return months[month - 1] || 'Unknown';
};

/**
 * Get short month name from month number
 * @param {number} month - Month number (1-12)
 * @returns {string} Short month name
 */
export const getShortMonthName = (month) => {
  const months = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
  ];
  return months[month - 1] || 'Unknown';
};
