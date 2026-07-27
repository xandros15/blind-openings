<script setup>
import {ref} from "vue";

const teams = ref([])
const doneThemes = ref([])
const teamThemes = ref([])
const rolledThemes = ref([])
const chooseTeam = ref(null)
const chooseAccount = ref(null)
const chooseTheme = ref(null)
const loading = ref(false)

fetch('/api/teams').then(async r => {
  if (r.ok) {
    teams.value = await r.json()
  }
})

function switchTeam(team) {
  chooseTeam.value = team
}

function nextRound() {
  doneThemes.value.push(Object.assign({}, chooseTheme.value)) //add to excluded
  rolledThemes.value = []
  chooseTeam.value = null
  chooseAccount.value = null
  chooseTheme.value = null
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

</script>

<template>
  <div class="section">
    <div class="container">
      <h1 class="title is-size-1">Panel</h1>
      <div class="box">
        <h2 class="title is-size-2">Wyświetlone/Przelosowane</h2>
        <div class="tag large mb-2 is-size-5 is-block is-warning" v-for="theme in doneThemes" :key="theme.id">
          {{ theme.name }}
        </div>
      </div>
      <div class="box" v-if="chooseTheme">
        <h2 class="title is-size-2">Wideo {{ chooseTheme.name }} {{ /OP\d+(?:v\d+)?/.exec(chooseTheme.path)?.[0] }}</h2>
        <video controls muted :key="chooseTheme.path">
          <source :src="`/videos/${chooseTheme.path}`" type="video/webm">
          Twoja przeglądarka nie obsługuje elementu video.
        </video>
        <div class="buttons">
          <button class="button is-success" @click="nextRound()">Następna runda</button>
        </div>
      </div>
      <div class="box" v-if="rolledThemes.length > 0">
        <h2 class="title is-size-2">Wybierz opening</h2>
        <h3 class="subtitle is-3">(Wylosowano z {{teamThemes.length}} openingów)</h3>
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
      <div class="box" v-if="chooseTeam">
        <h2 class="title is-size-2">Wybierz uczestnika z {{ chooseTeam.team_name }}</h2>
        <div class="buttons" v-if="chooseAccount === null">
          <button @click.prevent="chooseAccount = list.id" class="button is-info" v-for="list in chooseTeam.lists"
                  :key="list.id">
            {{ list.account_name }}
          </button>
        </div>
        <div class="buttons" v-else>
          <button @click.prevent="chooseAccount = list.id" class="button"
                  :class="chooseAccount === list.id ? 'is-warning' : 'is-success'" v-for="list in chooseTeam.lists"
                  :key="list.id">
            {{ list.account_name }}
          </button>
        </div>
        <div v-if="chooseAccount" class="buttons">
          <button class="button is-success" @click="rollTheme">Wylosuj</button>
        </div>
      </div>
      <div v-for="team in teams" :key="team.id" class="box">
        <h2 class="title is-size-2">{{ team.team_name }}</h2>
        <div class="mb-2" v-for="list in team.lists" :key="list.id">
          <b class="mr-2">{{ list.account_name }}</b>
          <span class="tag is-info">{{ list.service }}</span>
        </div>
        <button class="button is-success" @click.prevent="switchTeam(team)">Wybierz</button>
      </div>
      <div class="box">
        <button class="button is-success" :class="{'is-loading': loading}" @click.prevent="downloadListsFromForm"
                :disabled="loading">
          Pobierz listy z formularza
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped></style>
