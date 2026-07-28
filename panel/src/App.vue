<script setup>
import {computed, ref} from "vue";
import toastr from '@/toastr.js'
import Notifications from "@/Notifications.vue";

const teams = ref([])
const doneThemes = ref([])
const teamThemes = ref([])
const rolledThemes = ref([])
const chooseTeamId = ref(null);
const chooseTeam = computed(() => chooseTeamId.value ? teams.value.find(t => t.id === chooseTeamId.value) : null)
const chooseAccountId = ref(null);
const chooseAccount =  computed(() => chooseTeam.value.lists.find(a => a.id === chooseAccountId.value))
const chooseTheme = ref(null)
const loading = ref(false)
const showLeftPanel = ref(true)

fetch('/api/teams').then(async r => {
  if (r.ok) {
    teams.value = await r.json()
  }
})

function switchTeam(team) {
  chooseTeamId.value = team.id
}

function nextRound() {
  doneThemes.value.push(Object.assign({}, chooseTheme.value)) //add to excluded
  rolledThemes.value = []
  chooseTeamId.value = null
  chooseAccount.value = null
  chooseTheme.value = null
  showLeftPanel.value = true
}

function reshuffle(theme, index) {
  doneThemes.value.push(Object.assign({}, theme)) //add to excluded
  const rolled = getRandom(teamThemes.value)?.[0]
  if (rolled) {
    teamThemes.value = teamThemes.value.filter(tt => rolled?.id !== tt.id) //remove new roll from list
    rolledThemes.value[index] = rolled
  } else {
    rolledThemes.value.splice(index)
  }
}

function secureRandomInt(max) {
  const range = 256 ** 4;
  const limit = range - (range % max);
  let rand = 0;

  do {
    rand = crypto.getRandomValues(new Uint32Array(1))[0];
  } while (rand >= limit);

  return rand % max;
}

function getRandom(items, number = 3) {
  const newItems = [...items];
  const results = [];
  for (let i = 0; i < number && newItems.length > 0; i++) {
    const idx = secureRandomInt(newItems.length);
    results.push(newItems.splice(idx, 1)[0]);
  }

  return results;
}

function selectVideo(theme, path) {
  chooseTheme.value = Object.assign({}, {...theme, path})
}

async function rollTheme() {
  if (!chooseAccount.value || !chooseTeam.value) {
    return
  }

  teamThemes.value = await fetch('/api/find-themes', {
    method: 'POST',
    headers: {
      "Content-Types": 'application/json'
    },
    body: JSON.stringify({
      listIds: chooseTeam.value.lists.filter(l => l.id !== chooseAccount.value).map(l => l.id),
      excludedIds: doneThemes.value.map(dt => parseInt(dt.id)),
    }),
  }).then(r => r.ok ? r.json() : [])

  const rolled = getRandom(teamThemes.value) //roll 3 random
  teamThemes.value = teamThemes.value.filter(tt => !rolled.map(r => r.id).includes(tt.id)) //remove 3 rolled from list
  rolledThemes.value = rolled
}

async function downloadListsFromForm() {
  loading.value = true
  await fetch('/api/lists', {
    method: 'POST'
  })
  await fetch('/api/teams').then(async r => {
    if (r.ok) {
      teams.value = await r.json()
    }
  })
  loading.value = false
}

async function deleteTeam(teamId) {
  if (!confirm('Czy chcesz usunąć?')) {
    return;
  }
  loading.value = true
  const response = await fetch('/api/teams/' + teamId, {
    method: 'DELETE'
  })
  if (response.ok) {
    await fetch('/api/teams').then(async r => {
      if (r.ok) {
        teams.value = await r.json()
      }
    })
    toastr.success('Usunięto drużynę')
  } else {
    const data = await response.json()
    if (data?.message) {
      toastr.error(data.message)
    }
  }
  loading.value = false
}

async function deleteTeamList(teamId, teamListId) {
  if (!confirm('Czy chcesz usunąć?')) {
    return;
  }
  loading.value = true
  const response = await fetch('/api/teams/' + teamId + '/lists/' + teamListId, {
    method: 'DELETE'
  })
  if (response.ok) {
    await fetch('/api/teams').then(async r => {
      if (r.ok) {
        teams.value = await r.json()
      }
    })
    toastr.success('Usunięto listę')
  } else {
    const data = await response.json()
    if (data?.message) {
      toastr.error(data.message)
    }
  }
  loading.value = false
}

</script>

<template>
  <Notifications/>
  <div class="buttons is-position-fixed p-2">
    <button v-if="showLeftPanel" class="button is-small" @click.prevent="showLeftPanel = false">
      Ukryj
    </button>
    <button v-else class="button is-small" @click.prevent="showLeftPanel = true">
      Pokaż
    </button>
  </div>
  <div class="columns is-gapless pt-6">
    <div class="column is-3" v-if="showLeftPanel">
      <aside class="p-4">
        <div class="content">
          <div v-if="rolledThemes.length > 0 && !chooseTheme" class="">
            <h2 class="title is-size-4">Wyświetlone/Przelosowane</h2>
            <div class="" v-for="theme in doneThemes" :key="theme.id">
              <div class="tag large is-clipped is-warning is-size-5 mb-2 is-block is-warning" :title="theme.name">
                {{ theme.name }}
              </div>
            </div>
          </div>
          <div v-else>
            <div v-for="team in teams" :key="team.id">
              <div class="mb-1 is-flex is-justify-content-space-between">
                <div>
                  <button class="button is-danger is-small mr-2 bold" @click.prevent="deleteTeam(team.id)">x</button>
                  <span class="title is-size-5">{{ team.team_name }}</span>
                </div>
                <div class="buttons">
                  <button class="button is-small"
                          :class="chooseTeamId === team.id ? 'is-warning' : 'is-success'"
                          @click.prevent="switchTeam(team)">Wybierz
                  </button>
                </div>
              </div>
            </div>
            <hr>
            <div class="buttons">
              <button class="button is-success" :class="{'is-loading': loading}" @click.prevent="downloadListsFromForm"
                      :disabled="loading">
                Pobierz listy z formularza
              </button>
            </div>
          </div>
        </div>
      </aside>
    </div>
    <div class="column" :class="{'is-9': showLeftPanel}">
      <div class="p-3">
        <div class="box" v-if="chooseTheme">
          <h2 class="title is-size-2 has-text-centered">{{ chooseTheme.name }}
            <button class="button is-danger is-small is-float-right" @click.prevent="chooseTheme = null">wróć</button>
          </h2>
          <video controls muted :key="chooseTheme.path">
            <source :src="`/videos/${chooseTheme.path}`" type="video/webm">
            Twoja przeglądarka nie obsługuje elementu video.
          </video>
          <div class="buttons">
            <button class="button is-success" @click.prevent="nextRound()">Następna runda</button>
          </div>
        </div>

        <div class="box" v-else-if="rolledThemes.length > 0">
          <h2 class="title is-size-2">Wybierz opening
            <button class="is-small is-danger button is-float-right" @click.prevent="rolledThemes = []">wróć</button>
          </h2>
          <h3 class="subtitle is-3">(Wylosowano z {{ teamThemes.length }} openingów)</h3>
          <div class="columns">
            <div class="column" v-for="(theme, index) in rolledThemes" :key="theme.id">
              <div class="card">
                <div class="card-image">
                  <figure class="image">
                    <img :src="`/images/${theme.id}.webp`" alt="Placeholder image"/>
                  </figure>
                </div>
                <div class="card-content">
                  <div class="media">
                    <div class="media-content">
                      <div class="tags">
                        <a :href="resource.link" target="_blank" rel="nofollow" class="tag is-link"
                           v-for="resource in theme.resources" :key="resource.link">
                          {{ resource.site }}
                        </a>
                      </div>
                      <p class="title is-4">
                        {{ theme.name }}
                      </p>
                      <p class="subtitle is-6">{{ theme.year }}</p>
                      <button class="button is-warning" @click.prevent="reshuffle(theme, index)">Przelosuj</button>
                    </div>
                  </div>
                  <div class="buttons">
                    <button v-for="videoPath in theme.paths" @click.prevent="selectVideo(theme, videoPath)"
                            class="button is-success" :key="videoPath">
                      {{ /OP\d+(?:v\d+)?/.exec(videoPath)?.[0] }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="box" v-else-if="chooseTeam">
          <h2 class="title is-size-2">
            Wybierz uczestnika z {{ chooseTeam.team_name }}
          </h2>

          <div class="fixed-grid has-4-cols">

            <div class="grid">
              <div class="cell" v-for="list in chooseTeam.lists" :key="list.id">
                <div class="card account"
                     :class="chooseAccount === null ? '' : chooseAccountId === list.id ? 'selected-account' : 'unselected-account'">
                  <div class="card-header">
                    <div class="card-header-title">
                      <div>
                        <p class="title">
                        <span class="tag is-info">
                          {{ list.service }}
                        </span>
                          {{ list.account_name }}
                        </p>
                        <p class="subtitle">{{ list.openingsCount }} openingów</p>
                      </div>
                    </div>
                    <div class="card-header-icon">
                      <button @click.prevent="deleteTeamList(chooseTeam.id, list.id)" class="button is-danger is-small">
                        x
                      </button>
                    </div>
                  </div>
                  <div class="card-footer">
                    <button class="card-footer-item button" :class="chooseAccountId === list.id ? 'is-warning' : ''"
                            @click.prevent="chooseAccountId = list.id">
                      Wybierz
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div v-if="chooseAccount" class="buttons">
            <button class="button is-success" @click="rollTheme">Wylosuj</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.account {
  transition: transform .3s;
  transform: scale(.9);
}

.selected-account {
  transform: scale(1);
}

.unselected-account {
  transform: scale(.8);
}
</style>
