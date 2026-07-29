import { reactive } from 'vue'

const state = reactive({
    notifications: []
})

function notify(text, type = 'info') {
    state.notifications.push({id: crypto.randomUUID(), text, type})
}

function remove(id) {
    state.notifications = state.notifications.filter(n => n.id !== id)

}

const toast = {
    state,
    remove,
    error: text => notify(text, 'danger'),
    warning: text => notify(text, 'warning'),
    success: text => notify(text, 'success'),
    info: text => notify(text, 'info'),
}

export default toast
