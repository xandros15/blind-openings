<script>
export default {
  name: "Notification",
  props: {
    type: {
      required: true,
      type: String,
    }
  },
  created() {
    setTimeout(() => this.$emit('close'), 3000)
  },
  computed: {
    classes() {
      if (this.type === 'link') {
        return 'is-link';
      }
      if (this.type === 'primary') {
        return 'is-primary';
      }
      if (this.type === 'info') {
        return 'is-info';
      }
      if (this.type === 'success') {
        return 'is-success';
      }
      if (this.type === 'warning') {
        return 'is-warning';
      }
      if (this.type === 'danger') {
        return 'is-danger';
      }
    }
  }
}
</script>

<template>
  <div class="notification is-light" :class="classes">
    <div class="progress-bar"></div>
    <button class="delete" @click="$emit('close')"></button>
    <slot/>
  </div>
</template>

<style scoped>
.notification {
  padding-right: 3rem;
  overflow: hidden;
  position: relative;
}
.progress-bar {
  position: absolute;
  top: 0;
  left: 0;
  height: 4px;
  width: 100%;
  background: rgba(0, 0, 0, 0.6); /* czarny z przezroczystością */
  transform-origin: left;
  animation: progress 3s linear forwards;
}
@keyframes progress {
  from {
    transform: scaleX(1); /* pełny pasek */
  }
  to {
    transform: scaleX(0); /* znika do zera = "czas minął" */
  }
}
</style>
