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
    EXCELLENT: 100, // >= 100%
    WARNING: 90,    // >= 90% and < 100%
    POOR: 0         // < 90%
  },
  
  // Default values
  DEFAULTS: {
    BIMONTHLY_SPLIT_DATE: 15,
    WORKING_DAYS_PER_WEEK: 5,
    MAX_WEEKS_SELECTION: 12
  },
  
  // Chart settings
  CHART: {
    MIN_HEIGHT: 8, // Minimum hours to show on chart
    BAR_COLORS: {
      BILLABLE: '#4CAF50',
      NON_BILLABLE: '#2196F3'
    }
  },
  
  // Date formats
  DATE_FORMATS: {
    INPUT: 'YYYY-MM-DD',
    DISPLAY: 'MMM DD, YYYY',
    SHORT: 'MMM DD',
    WEEK_LABEL: 'MMM DD - MMM DD'
  },
  
  // API endpoints
  API_ENDPOINTS: {
    DASHBOARD: '/api/admin/iva-users/{userId}/worklog-dashboard',
    RECORDS: '/api/admin/iva-users/{userId}/timedoctor-records'
  }
};

/**
 * Work status types
 */
export const WORK_STATUS = {
  FULL_TIME: 'full-time',
  PART_TIME: 'part-time'
};

/**
 * Report category types
 */
export const REPORT_CATEGORIES = {
  BILLABLE: 'billable',
  NON_BILLABLE: 'non-billable'
};

/**
 * Date mode options
 */
export const DATE_MODES = {
  WEEKS: 'weeks',
  MONTHLY: 'monthly',
  BIMONTHLY: 'bimonthly',
  CUSTOM: 'custom'
};

/**
 * Performance status types
 */
export const PERFORMANCE_STATUS = {
  EXCELLENT: 'EXCELLENT',
  WARNING: 'WARNING',
  POOR: 'POOR'
};

/**
 * UI breakpoints (in pixels)
 */
export const BREAKPOINTS = {
  MOBILE: 768,
  TABLET: 1024,
  DESKTOP: 1200
};

/**
 * Chart configuration
 */
export const CHART_CONFIG = {
  COLORS: {
    PRIMARY: '#6366f1',
    SUCCESS: '#10b981',
    WARNING: '#f59e0b',
    ERROR: '#ef4444',
    INFO: '#06b6d4',
    SECONDARY: '#6b7280'
  },
  ANIMATION_DURATION: 300,
  HOVER_EFFECTS: {
    BRIGHTNESS: 1.1,
    OPACITY: 0.85,
    SCALE_X: 1.02
  }
};

/**
 * Pagination settings
 */
export const PAGINATION = {
  DEFAULT_PER_PAGE: 25,
  MAX_PER_PAGE: 100
};

/**
 * Error messages
 */
export const ERROR_MESSAGES = {
  LOAD_USER_DETAILS: 'Failed to load user details',
  LOAD_DASHBOARD: 'Failed to load dashboard data',
  INVALID_DATE_RANGE: 'Invalid date range selected',
  DATE_RANGE_TOO_LARGE: 'Date range cannot exceed 3 months (90 days)',
  NO_DATA: 'No data available for the selected period',
  NETWORK_ERROR: 'Network error occurred. Please try again.',
  UNKNOWN_ERROR: 'An unknown error occurred'
};

/**
 * Success messages
 */
export const SUCCESS_MESSAGES = {
  DATA_LOADED: 'Data loaded successfully',
  EXPORT_COMPLETE: 'Export completed successfully'
};

/**
 * Loading states
 */
export const LOADING_STATES = {
  INITIAL: 'Loading dashboard...',
  DASHBOARD: 'Loading dashboard data...',
  USER_DETAILS: 'Loading user details...',
  EXPORT: 'Exporting data...'
};

/**
 * Feature flags
 */
export const FEATURES = {
  BIMONTHLY_TABS: true,
  PERFORMANCE_TRACKING: true,
  CATEGORY_BREAKDOWN: true,
  EXPORT_FUNCTIONALITY: true,
  MOBILE_RESPONSIVE: true
};

/**
 * Accessibility settings
 */
export const A11Y = {
  ARIA_LABELS: {
    LOADING: 'Loading content',
    CLOSE_NOTIFICATION: 'Close notification',
    BACK_TO_LIST: 'Back to users list',
    VIEW_RECORDS: 'View detailed records',
    DATE_SELECTION: 'Date selection mode',
    CHART_BAR: 'Chart bar for {date}: {hours} hours'
  },
  FOCUS_OUTLINE: '2px solid var(--v-theme-primary)',
  FOCUS_OFFSET: '2px'
};

export default WORKLOG_CONFIG;
