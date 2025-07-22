// @/@core/utils/worklogConfig.js

/**
 * Configuration constants for worklog dashboard
 */
export const WORKLOG_CONFIG = {
  // Year settings
  START_YEAR: 2024, // First year available for reporting

  // Week calculation settings
  WEEK_1_2024_START: '2024-01-15', // Week 1 of 2024 starts on January 15, 2024
  WEEKS_PER_YEAR: 52,

  // Performance thresholds
  PERFORMANCE_THRESHOLDS: {
    EXCEEDED: 101, // >= 101%
    MEET: 99, // >= 99% and < 101%
    BELOW: 0, // < 99%
  },

  // Default values
  DEFAULTS: {
    BIMONTHLY_SPLIT_DATE: 15,
    WORKING_DAYS_PER_WEEK: 5,
    MAX_WEEKS_SELECTION: 12,
  },

  // Chart settings
  CHART: {
    MIN_HEIGHT: 8, // Minimum hours to show on chart
    BAR_COLORS: {
      BILLABLE: '#4CAF50',
      NON_BILLABLE: '#2196F3',
    },
  },

  // Date formats
  DATE_FORMATS: {
    INPUT: 'YYYY-MM-DD',
    DISPLAY: 'MMM DD, YYYY',
    SHORT: 'MMM DD',
    WEEK_LABEL: 'MMM DD - MMM DD',
  },

  // API endpoints
  API_ENDPOINTS: {
    DASHBOARD: '/api/admin/iva-users/{userId}/worklog-dashboard',
    RECORDS: '/api/admin/iva-users/{userId}/timedoctor-records',
  },
}

export default WORKLOG_CONFIG
