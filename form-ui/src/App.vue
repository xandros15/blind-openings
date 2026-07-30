<script setup>
import CustomModal from "@/CustomModal.vue";
import {ref} from "vue";
import toastr from '@/tools/toastr.js'
import Notifications from "@/Notifications.vue";
import AnimeList from "@/AnimeList.vue";
import TeamMateModal from "@/TeamMateModal.vue";

const teamMates = ref([])
const modal = ref(null)
const loading = ref(false)
const teamName = ref('')

function removeTeamMate(name, service) {
  teamMates.value = teamMates.value.filter(mate => mate.service !== service || mate.name !== name)
  toastr.success('Usunięto liste ' + name)
}

function addNewTeamMate(data) {
  teamMates.value.push(data)
  modal.value.close()
}

async function sendTeam() {
  if (!teamName.value || teamMates.value.length < 1) {
    toastr.warning('Nie możesz się zapisać bez nazwy drużyny i minimum 2 członków.')
  }

  loading.value = true
  //normalize ids
  const lists = teamMates.value.map(mate => {
    mate.items = mate.items.map(anime => {
      anime.id = parseInt(anime.id)
      return anime
    })
    return mate
  });

  const response = await fetch('/api/lists', {
    method: "POST",
    body: JSON.stringify({lists, name: teamName.value}),
    headers: {
      ContentType: 'application/json'
    }
  }).catch(e => {
    toastr.error('Nieoczekiwany błąd: ' + e)
    loading.value = false
  })
  if (!response.ok) {
    try {
      const payload = await response.json();
      if (payload.error) {
        toastr.error(payload.error)
      } else {
        toastr.error('Nie udało się zapisać do konkursu')
      }
    } catch {
      toastr.error('Nie udało się zapisać do konkursu')
    }
  } else {
    toastr.success('Zostaliście zapisani do konkursu')
  }
  loading.value = false
}
</script>

<template>
  <Notifications/>
  <div class="section">
    <div class="content">
      <h1 class="is-size-1">Zgłoś drużynę</h1>
      <form @submit.prevent="sendTeam">
        <div class="field">
          <label class="label" for="teamName">Nazwa drużyny</label>
          <div class="control">
            <input class="input" v-model="teamName" id="teamName" name="teamName" type="text" :disabled="loading"
                   required>
          </div>
        </div>
        <div class="mb-6">
          <button type="button" class="is-link button" @click="$refs.modal.open()"
                  :disabled="loading"
          >Dodaj uczestnika
          </button>
        </div>
        <div class="mb-2">
          <button class="is-success button" :class="{'is-loading': loading}"
                  :disabled="teamMates.length < 2 || teamName.length <= 0 || loading">
            Zgłoś
          </button>
          <div v-if="teamMates.length < 2 || teamName.length <= 0" class="is-small has-text-warning">Do wysłania zgłoszenia potrzebujesz minimum 2 uczestników (i nazwy drużyny)</div>
        </div>
      </form>
    </div>
    <div class="content" v-if="teamMates.length > 0">
      <h2 class="is-size-2">Listy:</h2>
      <AnimeList v-for="mate in teamMates" :service="mate.service" :name="mate.name" :items="mate.items"
                 @remove="removeTeamMate(mate.name, mate.service)" :locked="loading"
      />
    </div>
  </div>
  <CustomModal ref="modal" title="Teammate">
    <TeamMateModal @addTeamMate="addNewTeamMate" :teamMates="teamMates" @close="$refs.modal.close()"/>
  </CustomModal>
</template>

<style scoped></style>
