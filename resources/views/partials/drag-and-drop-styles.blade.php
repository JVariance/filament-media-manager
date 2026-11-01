{{-- For the grids that use partials.drag-and-drop --}}
<style>
    .media-dragging .media-card,
    [data-media-drop-folder].media-dragging {
        opacity: 0.5;
    }

    .media-drop-target {
        border-radius: 0.5rem;
        outline: 2px dashed var(--primary-500);
        outline-offset: 2px;
        background-color: color-mix(in oklab, var(--primary-500) 12%, transparent);
    }

    .media-drag-image {
        position: fixed;
        top: -100px;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        background-color: var(--primary-600);
        color: white;
        font-size: 0.875rem;
        font-weight: 600;
    }
</style>
