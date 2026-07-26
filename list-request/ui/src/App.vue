<script setup>
import CustomModal from "@/CustomModal.vue";
import {ref} from "vue";
import toastr from '@/tools/toastr.js'
import Notifications from "@/Notifications.vue";
import {anidb, anilist, kitsu, mal, malApi} from '@/tools/parsers.js';
import AnimeList from "@/AnimeList.vue";

const teamMates = ref([])
const modal = ref(null)

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


async function addTeamMate() {
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
  if (teamMates.value.find(tm => tm.service === mateService && tm.name === response.name)) {
    toastr.warning('Nie możesz dodać tej samej listy ponownie!')
    return
  }

  teamMates.value.push({service: mateService, ...response})
  file.value = null
  teamMateName.value = ''
  service.value = null
  modal.value.close()
}
</script>

<template>
  <Notifications/>
  <div class="section">
    <h1 class="is-size-1">Formularz drużynowy</h1>
    <form @submit="() => {}">
      <div class="field">
        <label class="label" for="teamName">Nazwa drużyny</label>
        <div class="control">
          <input class="input" id="teamName" name="teamName" type="text">
        </div>
      </div>
      <div class="mb-2">
        <button type="button" class="is-link button" @click="$refs.modal.open()">Dodaj uczestnika</button>
      </div>
      <div class="mb-2">
        <button class="is-link button">Zgłoś</button>
      </div>
    </form>
  </div>
  <section class="section" v-if="teamMates.length > 0">
    <div class="content">
      <h2 class="is-size-2">Listy:</h2>
      <AnimeList v-for="mate in teamMates" :service="mate.service" :name="mate.name" :items="mate.items"/>
    </div>
  </section>
  <CustomModal ref="modal" title="Teammate">
    <form @submit.prevent="addTeamMate">
      <div v-if="service === 'anidb'" class="notification is-info is-light">
        By wyeksportować listę należy wejść <a target="_blank" href="https://anidb.net/user/export"
                                               rel="nofollow">tutaj</a>, wybrać templatke xml, i zarequestować export.
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
            <select :disabled="loading" v-model="service">
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
          <input v-model="teamMateName" :disabled="loading" class="input" type="text" placeholder="Nazwa konta">
        </div>
      </div>
      <div v-if="['anidb', 'mal'].includes(service)" class="file">
        <label class="file-label">
          <input class="file-input" type="file" @change="loadFile" :disabled="loading" name="resume"/>
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
          <button class="button is-link is-light" @click.prevent="$refs.modal.close()">Anuluj</button>
        </div>
      </div>
    </form>
  </CustomModal>
</template>

<style scoped></style>
