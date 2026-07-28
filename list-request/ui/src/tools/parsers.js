import toastr from '@/tools/toastr.js'
import toast from '@/tools/toastr.js'

function handleError(e) {
    toastr.error('Błąd przy dodawaniu listy: ' + e)
}

async function isGzip(file) {
    const buffer = await file.slice(0, 2).arrayBuffer()
    const bytes = new Uint8Array(buffer)
    return bytes[0] === 0x1f && bytes[1] === 0x8b
}

function decompressGzip(file) {
    const ds = new DecompressionStream('gzip')
    const decompressedStream = file.stream().pipeThrough(ds)
    return new Response(decompressedStream)
}

export const malApi = async name => {
    const url = '/api/mal/' + encodeURIComponent(name)
    const items = await fetch(url).then(r => {
        if (r.status === 404) {
            toastr.error('Nie znaleziono konta')
            return false
        }

        if (!r.ok) {
            toastr.error('Błąd przy dodawaniu listy')
            return false
        }
        return r.json()
    }).then(r => {
        if (r === false) {
            return r
        }
        if (r && r.length > 0) {
            return r
        } else {
            toastr.warning('Lista jest pusta')
            return false
        }
    }).catch(handleError)

    if (!items) {
        return false
    }

    return {items, name}
}

export const mal = async file => {

    const ALLOWED = [
        'Completed',
        'Watching',
    ]
    try {
        const text = await isGzip(file) ? await decompressGzip(file).text() : await file.text()
        const dom = await (new window.DOMParser()).parseFromString(text, 'text/xml')
        const items = []
        dom.querySelectorAll('anime').forEach(anime => {
            const entry = {
                name: anime.querySelector('series_title').textContent,
                id: anime.querySelector('series_animedb_id').textContent,
                status: anime.querySelector('my_status').textContent,
                url: 'https://myanimelist.net/anime/' + anime.querySelector('series_animedb_id').textContent,
            }
            if (ALLOWED.indexOf(entry.status) !== -1) {
                items.push(entry)
            }
        })
        const name = dom.querySelector('myinfo user_name').textContent
        if (name && items.length > 0) {
            return {items, name}
        } else {
            toastr.warning('Lista jest pusta')
            return false
        }
    } catch (e) {
        console.error(e)
        toastr.error('Błąd przy dodawaniu listy: ' + e)
        return false
    }
}

export const anilist = async userName => {
    const query = `
query ($userName: String) {
  MediaListCollection(userId: 0, userName: $userName, type: ANIME, status_in: [COMPLETED, CURRENT, REPEATING]) {
    lists {
      entries {
        ...mediaListEntry
      }
    }
  }
}

fragment mediaListEntry on MediaList {
  media {
    id
    title {
      romaji
      english
    }
    coverImage {
      large
    }
  }
  status
}
`
    const variables = {
        userName,
    }

    const url = 'https://graphql.anilist.co',
        options = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                query: query,
                variables: variables
            })
        }

    return fetch(url, options)
        .then(handleResponse)
        .then(handleData)
        .then(items => {
            if (userName && items.length > 0) {
                return {items, name: userName}
            } else {
                toastr.warning('Lista jest pusta')
                return false
            }
        })
        .catch(handleError)

    function handleResponse(response) {
        if (response.status === 404) {
            throw 'Nie znaleziono konta.';
        }

        return response.json().then(function (json) {
            return response.ok ? json : Promise.reject(json)
        })
    }

    function handleData({data}) {
        const items = []
        const uniq = []
        for (const {entries} of data.MediaListCollection.lists) {
            for (const {status, media} of entries) {
                if (uniq.indexOf(media.id) !== -1) {
                    continue
                }
                uniq.push(media.id)
                items.push({
                    id: media.id,
                    url: 'https://anilist.co/anime/' + media.id,
                    image: media.coverImage.large,
                    status: status[0].toUpperCase() + status.substring(1).toLowerCase(),
                    name: media.title.romaji
                })
            }
        }

        return items
    }
}


export const anidb = async file => {
    function parseTar(arrayBuffer) {
        const files = {}
        const view = new Uint8Array(arrayBuffer)
        let offset = 0

        const decoder = new TextDecoder('utf-8')

        while (offset + 512 <= view.length) {
            const header = view.slice(offset, offset + 512)
            const nameBytes = header.slice(0, 100)
            const name = decoder.decode(nameBytes).replace(/\0.*$/, '')

            if (!name) {
                break
            }
            const sizeBytes = header.slice(124, 136)
            const sizeStr = decoder.decode(sizeBytes).replace(/\0.*$/, '').trim()
            const size = parseInt(sizeStr, 8) || 0

            const dataStart = offset + 512
            files[name] = view.slice(dataStart, dataStart + size)
            const blocks = Math.ceil(size / 512)
            offset = dataStart + blocks * 512
        }

        return files
    }

    const ALLOWED = [
        'complete',
        'watched',
    ]
    try {
        let text = ''
        if (await isGzip(file)) {
            let files = parseTar(
                await decompressGzip(file).arrayBuffer()
            );
            if (!files['mylist.xml']) {
                toast.error('Podany plik nie posiada mylist.xml.')
                return
            }
            text = new TextDecoder('utf-8').decode(files['mylist.xml'])
            files = null
        } else {
            text = await file.text()
        }

        const dom = await (new window.DOMParser()).parseFromString(text, 'text/xml')
        const items = []

        dom.querySelectorAll('anime').forEach(anime => {
            const entry = {
                name: anime.querySelector('name').textContent,
                id: anime.querySelector('id').textContent,
                status: 'not supported',
                url: 'https://anidb.net/anime/' + anime.querySelector('id').textContent,
            }
            for (const allow of ALLOWED) {
                if (anime.hasAttribute(allow)) {
                    items.push(entry)
                    break
                }
            }
        })
        const name = dom.querySelector('mylist').getAttribute('user')

        if (name && items.length > 0) {
            return {items, name}
        } else {
            toastr.warning('Lista jest pusta')
            return false
        }
    } catch (e) {
        console.error(e)
        toastr.error('Błąd przy dodawaniu listy: ' + e)
        return false
    }
}

export const kitsu = async name => {
    const url = 'https://kitsu.io/api/edge/library-entries'
    const options = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/vnd.api+json',
        }
    }

    const requestList = async userId => {
        const params = new URLSearchParams({
            'fields[anime]': 'slug,posterImage,canonicalTitle',
            'fields[libraryEntries]': 'status',
            'filter[kind]': 'anime',
            'filter[status]': 'completed,current',
            'filter[user_id]': userId,
            'include': 'anime',
            'page[limit]': 500,
        })
        // Make the HTTP Api request
        return fetch(url + '?' + params.toString(), options)
            .then(handleResponse)
            .then(handleData)
            .catch(handleError)
    }

    const requestUser = async name => {
        const params = new URLSearchParams({
            'filter[slug]': name,
            'fields[user]': 'id',
        })
        return await fetch('https://kitsu.io/api/edge/users?' + params.toString())
            .then(handleResponse)
            .then(response => response.data[0].id)
            .catch((error) => {
                toastr.error('User not found')
                console.error(error)
                return false
            })
    }

    function handleResponse(response) {
        return response.json().then(function (json) {
            return response.ok ? json : Promise.reject(json)
        })
    }

    async function handleData(response) {
        let items = []
        for (const {id, attributes} of response.included) {
            items.push({
                id,
                url: 'https://kitsu.io/anime/' + attributes.slug,
                image: attributes.posterImage.medium,
                status: 'not supported',
                name: attributes.canonicalTitle
            })
        }
        if (response.links.next) {
            const nextPage = await fetch(response.links.next, options)
                .then(handleResponse)
                .then(handleData)
                .catch(handleError)
            items = items.concat(nextPage)
        }

        return items
    }

    const userId = await requestUser(name)

    return userId ? await requestList(userId).then(items => {
        if (name && items.length > 0) {
            return {items, name}
        } else {
            toastr.warning('Lista jest pusta')
            return false
        }
    }) : false
}
