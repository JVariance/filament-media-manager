{{--
    The Alpine data of a grid whose media and folders can be dragged onto folders, used as x-data="@include(...)",
    so it must not contain double quotes. The grid holds:
    - data-drop-folder-ids: the folders the media of the grid can be moved to
    - data-top-level-url: the folders list; dropping a folder on its breadcrumb moves it to the top level (id 0)
    A folder that can be moved has its own targets in data-folder-targets. Folders accept drops through
    data-media-drop-folder, the breadcrumbs through the folder_id of their links.
--}}
{
    dragged: null,
    target: null,
    controller: null,

    init() {
        this.controller = new AbortController()

        const options = { signal: this.controller.signal }

        // The breadcrumbs are outside the table, so the events are handled on the document
        document.addEventListener('dragover', (event) => this.over(event), options)
        document.addEventListener('drop', (event) => this.drop(event), options)
    },

    destroy() {
        this.controller?.abort()
    },

    startMedia(event, key) {
        // The selected media on the page when the medium is one of them, otherwise the medium alone
        const keys = isRecordSelected(key)
            ? Array.from($el.querySelectorAll('.media-item-checkbox'))
                .map((checkbox) => checkbox.value)
                .filter((selectedKey) => isRecordSelected(selectedKey))
            : [key]

        this.dragged = { type: 'media', keys, targets: JSON.parse($el.dataset.dropFolderIds ?? '[]') }

        this.begin(event, keys.length)

        this.$nextTick(() => keys.forEach((draggedKey) => $el.querySelector(`[data-media-key='${draggedKey}']`)?.classList.add('media-dragging')))
    },

    startFolder(event, id) {
        this.dragged = { type: 'folder', id, targets: JSON.parse(event.currentTarget.dataset.folderTargets) }

        this.begin(event, 1)

        const folder = event.currentTarget

        this.$nextTick(() => folder.classList.add('media-dragging'))
    },

    begin(event, count) {
        event.dataTransfer.effectAllowed = 'move'
        event.dataTransfer.setData('application/x-filament-media-manager', JSON.stringify(this.dragged))

        if (count > 1) {
            const image = document.createElement('div')
            image.className = 'media-drag-image'
            image.textContent = count
            document.body.appendChild(image)
            event.dataTransfer.setDragImage(image, 0, 0)
            setTimeout(() => image.remove())
        }
    },

    end() {
        $el.querySelectorAll('.media-dragging').forEach((item) => item.classList.remove('media-dragging'))
        this.highlight(null)
        this.dragged = null
    },

    folderOf(event) {
        const element = event.target.closest?.('[data-media-drop-folder], .fi-breadcrumbs a[href]')

        if (! element) {
            return null
        }

        let id = null

        if (element.dataset.mediaDropFolder !== undefined) {
            id = Number(element.dataset.mediaDropFolder)
        } else {
            const url = new URL(element.href, window.location.href)
            const topLevel = new URL($el.dataset.topLevelUrl, window.location.href)

            if (url.searchParams.has('folder_id')) {
                id = Number(url.searchParams.get('folder_id'))
            } else if ((url.origin + url.pathname === topLevel.origin + topLevel.pathname) && (! url.search)) {
                id = 0
            }
        }

        return (id !== null) && this.dragged.targets.includes(id) ? { element, id } : null
    },

    highlight(element) {
        this.target?.classList.remove('media-drop-target')
        this.target = element
        this.target?.classList.add('media-drop-target')
    },

    over(event) {
        if (! this.dragged) {
            return
        }

        const folder = this.folderOf(event)

        this.highlight(folder?.element ?? null)

        if (folder) {
            event.preventDefault()
            event.dataTransfer.dropEffect = 'move'
        }
    },

    drop(event) {
        const folder = this.dragged ? this.folderOf(event) : null

        if (! folder) {
            return
        }

        event.preventDefault()

        if (this.dragged.type === 'folder') {
            $wire.moveFolderToFolder(this.dragged.id, folder.id)
        } else {
            $wire.moveMediaToFolder(this.dragged.keys, folder.id)
        }

        this.end()
    },
}
