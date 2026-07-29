<template>
  <div class="modal" tabindex="-1" ref="modal">
    <div class="modal-background" @click="close"></div>
    <div class="modal-card">
      <div class="modal-card-head">
        <h2 class="is-size-2 modal-card-title">{{ title }}</h2>
      </div>
      <div class="modal-card-body">
        <slot/>
      </div>
      <div class="modal-card-foot"></div>
    </div>
    <button class="modal-close is-large" aria-label="close" @click="close"></button>
  </div>
</template>

<script>

export default {
  name: 'CustomModal',
  props: {
    title: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      modal: null,
    }
  },
  methods: {
    open() {
      this.modal = this.$refs.modal
      this.modal.classList.add('is-active')
    },
    close() {
      this.modal.classList.remove('is-active')
      this.modal = null
    },
  },
}
</script>
