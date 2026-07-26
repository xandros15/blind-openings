<template>
  <div v-if="items.length > 0" class="mb-5">
    <h3 class="is-size-3">
      <button class="mr-2 button is-small is-danger"
              @click="confirm('Czy na pewno chcesz usunąć liste ' + name) && $emit('remove')"
              :disabled="locked"
      >Usuń</button>
      <span class="mr-2">Lista {{ name }} ({{ items.length }} animców)</span>
      <span class="tag is-info">{{ service }}</span>
    </h3>
    <div class="mb-1">
      <button class="button is-small is-info" @click="toggle">
        <span class="rotable" :class="{'is-open': isHidden}">▼</span>
      </button>
    </div>
    <ol :class="{'is-hidden': isHidden}">
      <li v-for="anime in items" :key="anime.id">
        <a :href="anime.url" target="_blank">{{ anime.name }}</a>
      </li>
    </ol>
  </div>
</template>

<script>
export default {
  name: 'AnimeList',
  props: {
    service: {type: String, required: true},
    items: {type: Array, required: true},
    locked: {type: Boolean, required: true},
    name: {type: String, required: true},
  },
  emits: ['remove'],
  data() {
    return {
      tempName: '',
      isEditing: false,
      isHidden: true,
    }
  },
  methods: {
    toggle() {
      this.isHidden = !this.isHidden
    },
    confirm(text) {
      return confirm(text)
    }
  }
}
</script>

<style scoped>
.rotable {
  display: block;
  transition: transform .3s;
}

.is-open {
  transform: rotate(-90deg);
}

.is-hidden {
  display: none;
}
</style>
