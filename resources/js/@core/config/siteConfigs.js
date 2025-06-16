// @/@core/utils/siteConfigs.js (Updated with worklog configurations)

export const appName = import.meta.env.VITE_APP_NAME || "BillTrack JS";
export const appLongName = import.meta.env.VITE_APP_LONG_NAME || "Billable Hours Management System JS";
export const copyright = `<strong>Copyright © ${new Date().getFullYear()} </strong>. ${appName} - All rights reserved.`;
export const appVersion = import.meta.env.VITE_APP_VERSION || "1.2.04.2025";
export const appAuthor = import.meta.env.VITE_APP_AUTHOR || "Genashtim";

// // 👉 Worklog Dashboard Configuration
// export const worklogConfig = {
//   // Date range settings
//   startYear: 2024, // Can be changed to adjust the earliest year for reports
//   week1Start2024: '2024-01-15', // Week 1 of 2024 starts on January 15
  
//   // Performance thresholds
//   performance: {
//     excellent: 100, // >= 100%
//     warning: 90,    // >= 90% and < 100%
//     poor: 0         // < 90%
//   },
  
//   // Default work hours
//   defaultHours: {
//     fullTime: 40,
//     partTime: 20
//   },
  
//   // Chart settings
//   chart: {
//     minHeight: 8,
//     colors: {
//       billable: '#4CAF50',
//       nonBillable: '#2196F3',
//       primary: '#6366f1',
//       success: '#10b981',
//       warning: '#f59e0b',
//       error: '#ef4444',
//       info: '#06b6d4',
//       secondary: '#6b7280'
//     }
//   },
  
//   // Date formats
//   dateFormats: {
//     input: 'YYYY-MM-DD',
//     display: 'MMM DD, YYYY',
//     short: 'MMM DD',
//     weekLabel: 'MMM DD - MMM DD'
//   },
  
//   // Bimonthly settings
//   bimonthly: {
//     defaultSplitDate: 15, // Default date to split month (can be 1-28)
//     allowedSplitRange: { min: 1, max: 28 }
//   },
  
//   // Pagination
//   pagination: {
//     defaultPerPage: 25,
//     maxPerPage: 100
//   },
  
//   // Mobile breakpoint
//   mobileBreakpoint: 768
// };

// // 👉 API Configuration
// export const apiConfig = {
//   baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
//   timeout: 30000,
//   retryAttempts: 3,
//   retryDelay: 1000
// };

// // 👉 Feature Flags
// export const features = {
//   worklogDashboard: true,
//   bimonthlyReports: true,
//   performanceTracking: true,
//   categoryBreakdown: true,
//   timeDoctorIntegration: true,
//   exportFunctionality: true,
//   mobileResponsive: true,
//   darkMode: false,
//   notifications: true,
//   realTimeSync: false
// };

// // 👉 UI Configuration
// export const uiConfig = {
//   theme: {
//     primary: '#6366f1',
//     secondary: '#6b7280',
//     success: '#10b981',
//     warning: '#f59e0b',
//     error: '#ef4444',
//     info: '#06b6d4'
//   },
  
//   layout: {
//     sidebar: {
//       width: 260,
//       collapsedWidth: 78
//     },
//     navbar: {
//       height: 64
//     },
//     footer: {
//       height: 56
//     }
//   },
  
//   animation: {
//     duration: 300,
//     easing: 'ease-in-out'
//   },
  
//   breakpoints: {
//     xs: 0,
//     sm: 600,
//     md: 960,
//     lg: 1280,
//     xl: 1920
//   }
// };

// // 👉 Notification Configuration
// export const notificationConfig = {
//   position: 'top-right',
//   timeout: 5000,
//   maxVisible: 5,
//   types: {
//     success: {
//       color: '#10b981',
//       icon: 'ri-checkbox-circle-line'
//     },
//     error: {
//       color: '#ef4444',
//       icon: 'ri-error-warning-line'
//     },
//     warning: {
//       color: '#f59e0b',
//       icon: 'ri-alert-line'
//     },
//     info: {
//       color: '#06b6d4',
//       icon: 'ri-information-line'
//     }
//   }
// };

// // 👉 Error Messages
// export const errorMessages = {
//   network: 'Network error occurred. Please check your connection.',
//   unauthorized: 'You are not authorized to perform this action.',
//   forbidden: 'Access denied. Please contact your administrator.',
//   notFound: 'The requested resource was not found.',
//   validation: 'Please check your input and try again.',
//   server: 'Server error occurred. Please try again later.',
//   timeout: 'Request timeout. Please try again.',
//   unknown: 'An unexpected error occurred.'
// };

// // 👉 Success Messages
// export const successMessages = {
//   save: 'Data saved successfully.',
//   update: 'Data updated successfully.',
//   delete: 'Data deleted successfully.',
//   create: 'Data created successfully.',
//   import: 'Data imported successfully.',
//   export: 'Data exported successfully.',
//   sync: 'Synchronization completed successfully.'
// };

// // 👉 Validation Rules
// export const validationRules = {
//   email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
//   phone: /^[\+]?[1-9][\d]{0,15}$/,
//   url: /^https?:\/\/.+/,
  
//   // Custom rules for worklog
//   hours: {
//     min: 0,
//     max: 24,
//     precision: 2
//   },
  
//   dateRange: {
//     maxDays: 90, // Maximum 3 months
//     minDays: 1
//   },
  
//   workWeek: {
//     maxWeeks: 12,
//     minWeeks: 1
//   }
// };

// // 👉 Default Settings
// export const defaultSettings = {
//   language: 'en',
//   timezone: 'UTC',
//   dateFormat: 'MM/DD/YYYY',
//   timeFormat: '24h',
//   currency: 'USD',
  
//   // User preferences
//   userPreferences: {
//     dashboardRefreshInterval: 300000, // 5 minutes
//     autoSave: true,
//     notifications: true,
//     theme: 'light'
//   },
  
//   // Worklog specific defaults
//   worklog: {
//     defaultView: 'weeks',
//     defaultWeekCount: 1,
//     showWeekends: true,
//     autoRefresh: false
//   }
// };

// // 👉 Permissions
// export const permissions = {
//   worklog: {
//     view: 'worklog.view',
//     create: 'worklog.create',
//     update: 'worklog.update',
//     delete: 'worklog.delete',
//     export: 'worklog.export'
//   },
  
//   users: {
//     view: 'users.view',
//     create: 'users.create',
//     update: 'users.update',
//     delete: 'users.delete',
//     manage: 'users.manage'
//   },
  
//   dashboard: {
//     view: 'dashboard.view',
//     admin: 'dashboard.admin'
//   },
  
//   reports: {
//     view: 'reports.view',
//     generate: 'reports.generate',
//     export: 'reports.export'
//   },
  
//   settings: {
//     view: 'settings.view',
//     update: 'settings.update',
//     system: 'settings.system'
//   }
// };

// // 👉 Routes Configuration
// export const routeConfig = {
//   auth: {
//     login: '/auth/login',
//     logout: '/auth/logout',
//     register: '/auth/register',
//     forgotPassword: '/auth/forgot-password'
//   },
  
//   dashboard: {
//     home: '/',
//     analytics: '/analytics'
//   },
  
//   worklog: {
//     dashboard: '/worklog/dashboard/:id',
//     records: '/worklog/records/:id',
//     reports: '/worklog/reports'
//   },
  
//   users: {
//     list: '/users',
//     detail: '/users/:id',
//     create: '/users/create',
//     edit: '/users/:id/edit'
//   },
  
//   settings: {
//     general: '/settings',
//     users: '/settings/users',
//     system: '/settings/system'
//   }
// };

// // 👉 Storage Keys
// export const storageKeys = {
//   authToken: 'auth_token',
//   userPreferences: 'user_preferences',
//   dashboardFilters: 'dashboard_filters',
//   recentSearches: 'recent_searches',
//   theme: 'app_theme',
//   language: 'app_language'
// };

// // 👉 Environment Configuration
// export const environmentConfig = {
//   isDevelopment: import.meta.env.DEV,
//   isProduction: import.meta.env.PROD,
//   apiURL: import.meta.env.VITE_API_URL,
//   appURL: import.meta.env.VITE_APP_URL,
//   debug: import.meta.env.VITE_DEBUG === 'true'
// };

// // 👉 Export all configurations as default
// export default {
//   appName,
//   appLongName,
//   copyright,
//   appVersion,
//   appAuthor,
//   worklogConfig,
//   apiConfig,
//   features,
//   uiConfig,
//   notificationConfig,
//   errorMessages,
//   successMessages,
//   validationRules,
//   defaultSettings,
//   permissions,
//   routeConfig,
//   storageKeys,
//   environmentConfig
// };
