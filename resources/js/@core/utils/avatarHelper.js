// @/@core/utils/avatarHelper.js
import defaultAvatar from '@images/avatars/avatar.png'

/**
 * Convert email to filename format
 * @param {string} email - User email
 * @returns {string} - Filename format
 */
function emailToFileName(email) {
  return email.replace(/[^a-zA-Z0-9]/g, '_')
}

/**
 * Get avatar URL for user
 * @param {string} email - User email
 * @returns {string} - Avatar URL or default avatar
 */
export function getAvatarUrl(email) {
  if (!email) {
    return defaultAvatar
  }

  const filename = emailToFileName(email) + '.jpg'
  const avatarUrl = `/storage/avatars/${filename}`
  
  return new Promise((resolve) => {
    // Check if avatar exists by trying to load it
    const img = new Image()
    img.onload = () => resolve(avatarUrl)
    img.onerror = () => resolve(defaultAvatar)
    img.src = avatarUrl
  })
}

/**
 * Get avatar URL synchronously (use when you know avatar exists)
 * @param {string} email - User email
 * @returns {string} - Avatar URL
 */
export function getAvatarUrlSync(email) {
  if (!email) {
    return defaultAvatar
  }

  const filename = emailToFileName(email) + '.jpg'
  return `/storage/avatars/${filename}`
}

/**
 * Check if avatar exists for email
 * @param {string} email - User email
 * @returns {Promise<boolean>} - True if avatar exists
 */
export function checkAvatarExists(email) {
  if (!email) {
    return Promise.resolve(false)
  }

  const filename = emailToFileName(email) + '.jpg'
  const avatarUrl = `/storage/avatars/${filename}`
  
  return new Promise((resolve) => {
    const img = new Image()
    img.onload = () => resolve(true)
    img.onerror = () => resolve(false)
    img.src = avatarUrl
  })
}
