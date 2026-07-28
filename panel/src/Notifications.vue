<script setup>
import toast from '@/toastr.js'
import Notification from "@/Notification.vue";
</script>

<template>
  <div class="notifications">
    <Notification v-for="notice in toast.state.notifications"
                  :type="notice.type"
                  :key="notice.id"
                  @click.once.prevent="toast.remove(notice.id)"
                  @close="toast.remove(notice.id)"
    >{{ notice.text }}
    </Notification>
  </div>
</template>

<style scoped>
.notifications {
  position: fixed;
  z-index: 9999;
  margin: 2rem;
  padding: .3rem;
}
</style>
