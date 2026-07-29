<script setup>

import {ref} from "vue";
import toastr from "@/tools/toastr.js";
import {anidb, anilist, kitsu, mal, malApi} from '@/tools/parsers.js';

const emit = defineEmits(['addTeamMate', 'close'])
const props = defineProps(['teamMates'])

const service = ref(null)
const file = ref(null)
const teamMateName = ref('')
const loading = ref(false)

function loadFile(e) {
  const files = e.target.files || e.dataTransfer.files
  if (files.length > 0) {
    file.value = files[0]
  }
}

async function addTeamMate() {
  function serviceFactory(type, data) {
    if (['mal', 'anidb'].includes(type) && !(data instanceof File)) {
      toastr.warning('Nie został dodany plik!')
      return false
    } else if (['malApi', 'kitsu', 'anilist'].includes(type) && (typeof data !== 'string' || data.length <= 0)) {
      toastr.warning('Nie została wpisana nazwa konta!')
      return false;
    }
    if (type === 'mal') {
      return mal(data)
    }
    if (type === 'anidb') {
      return anidb(data)
    }
    if (type === 'malApi') {
      return malApi(data)
    }
    if (type === 'kitsu') {
      return kitsu(data)
    }
    if (type === 'anilist') {
      return anilist(data)
    }
  }

  loading.value = true
  const response = await serviceFactory(
      service.value,
      ['mal', 'anidb'].includes(service.value) ? file.value : teamMateName.value
  )
  loading.value = false
  if (!response) {
    return
  }

  const mateService = service.value === 'malApi' ? 'mal' : service.value;
  if (props.teamMates.find(tm => tm.service === mateService && tm.name === response.name)) {
    toastr.warning('Nie możesz dodać tej samej listy ponownie!')
    return
  }

  emit('addTeamMate', {service: mateService, ...response})
  toastr.success('Dodano liste ' + response.name)
  file.value = null
  teamMateName.value = ''
  service.value = null
}
</script>

<template>
  <form @submit.prevent="addTeamMate">
    <div v-if="service === 'anidb'" class="notification is-info is-light">
      By wyeksportować listę należy wejść <a target="_blank" href="https://anidb.net/user/export"
                                             rel="nofollow">tutaj</a>, wybrać templatke <code>xml</code>, i
      zarequestować export.
      Po około 1min zostanie wysłane powiadomienie z linkiem do pobrania.
    </div>
    <div v-else-if="service === 'mal'" class="notification is-info is-light">
      By wyeksportować listę należy wejść <a target="_blank" href="https://myanimelist.net/panel.php?go=export"
                                             rel="nofollow">tutaj</a>, wybrać <code>anime list</code> i zatwierdzić
      pobranie.
    </div>
    <div class="field">
      <label class="label">Serwis z listą</label>
      <div class="control">
        <div class="select">
          <select :disabled="loading" v-model="service" required>
            <option :value="null" disabled>Wybierz serwis</option>
            <option value="malApi">MyAnimeList</option>
            <option value="anilist">AniList</option>
            <option value="kitsu">Kitsu</option>
            <option value="anidb">Anidb</option>
            <option value="mal">MyAnimeList (z pliku)</option>
          </select>
        </div>
      </div>
    </div>
    <div v-if="['malApi', 'kitsu', 'anilist'].includes(service)" class="field">
      <label class="label">Nazwa konta</label>
      <div class="control">
        <input v-model="teamMateName" :disabled="loading" class="input" type="text" placeholder="Nazwa konta" required>
      </div>
    </div>
    <div v-if="['anidb', 'mal'].includes(service)" class="file">
      <label class="file-label">
        <input class="file-input" type="file" @change="loadFile" :disabled="loading" name="resume" required/>
        <span class="file-cta">
              <span class="file-icon">
                <i class="fas fa-upload"></i>
              </span>
              <span class="file-label"> {{ file ? file.name : 'Wybierz plik z listą…' }} </span>
            </span>
      </label>
    </div>
    <div class="field is-grouped">
      <div class="control">
        <button class="button is-link" :class="{'is-loading': loading}" :disabled="loading">Dodaj</button>
      </div>
      <div class="control">
        <button class="button is-link is-light" @click.prevent="emit('close')">Anuluj</button>
      </div>
    </div>
  </form>
</template>

<style scoped>

</style>
