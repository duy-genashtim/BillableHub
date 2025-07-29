<script setup>
import { useAuthStore } from '@/@core/stores/auth'
import { getAvatarUrl } from '@/@core/utils/avatarHelper'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

const user = computed(() => authStore.getUser)
const avatarUrl = ref(null)

// Load avatar URL
watchEffect(async () => {
  if (user.value?.email) {
    avatarUrl.value = await getAvatarUrl(user.value.email)
  }
})

// Generate initials from name for fallback
const userInitials = computed(() => {
  if (!user.value?.name) return '?'
  return user.value.name
    .split(' ')
    .map(word => word.charAt(0))
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

// Generate a consistent color based on user name
const avatarColor = computed(() => {
  if (!user.value?.name) return 'primary'

  const colors = ['primary', 'secondary', 'success', 'info', 'warning', 'error']
  const hash = user.value.name.split('').reduce((a, b) => {
    a = ((a << 5) - a) + b.charCodeAt(0)
    return a & a
  }, 0)

  return colors[Math.abs(hash) % colors.length]
})

const handleLogout = async () => {
  try {
    await authStore.logout()
    router.push('/login')
  } catch (error) {
    console.error('Logout failed:', error)
  }
}
</script>

<template>
  <VBadge v-if="user" dot location="bottom right" offset-x="3" offset-y="3" color="success" bordered>
    <VAvatar class="cursor-pointer" :color="avatarUrl ? undefined : avatarColor" variant="tonal" size="40">
      <VImg v-if="avatarUrl" :src="avatarUrl" :alt="user.name" />
      <span v-else class="text-white font-weight-bold text-sm">
        {{ userInitials }}
      </span>

      <!-- SECTION Menu -->
      <VMenu activator="parent" width="230" location="bottom end" offset="14px">
        <VList>
          <!-- 👉 User Avatar & Name -->
          <VListItem>
            <template #prepend>
              <VListItemAction start>
                <VBadge dot location="bottom right" offset-x="3" offset-y="3" color="success">
                  <VAvatar :color="avatarUrl ? undefined : avatarColor" variant="tonal" size="40">
                    <VImg v-if="avatarUrl" :src="avatarUrl" :alt="user.name" />
                    <span v-else class="text-white font-weight-bold text-sm">
                      {{ userInitials }}
                    </span>
                  </VAvatar>
                </VBadge>
              </VListItemAction>
            </template>

            <VListItemTitle class="font-weight-semibold">
              {{ user.name }}
            </VListItemTitle>
            <VListItemSubtitle class="text-xs">{{ user.email }}</VListItemSubtitle>
          </VListItem>
          <VDivider class="my-2" />

          <!-- 👉 Profile -->
          <!-- <VListItem link>
            <template #prepend>
              <VIcon class="me-2" icon="ri-user-line" size="22" />
            </template>

            <VListItemTitle>Profile</VListItemTitle>
          </VListItem> -->

          <!-- 👉 Settings -->
          <!-- <VListItem to="/account-settings">
            <template #prepend>
              <VIcon class="me-2" icon="ri-settings-4-line" size="22" />
            </template>

            <VListItemTitle>Settings</VListItemTitle>
          </VListItem> -->

          <!-- Divider -->
          <!-- <VDivider class="my-2" /> -->

          <!-- 👉 Logout -->
          <VListItem @click="handleLogout">
            <template #prepend>
              <VIcon class="me-2" icon="ri-logout-box-r-line" size="22" />
            </template>

            <VListItemTitle>Logout</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
      <!-- !SECTION -->
    </VAvatar>
  </VBadge>
</template>
