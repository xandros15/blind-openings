<template>
  <div v-if="items.length > 0" class="mb-5">
    <h3 class="is-size-3">
      <span>Lista {{ name }} ({{items.length}} animców) <code>{{service}}</code></span>
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
    name: {type: String, required: true},
  },
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
