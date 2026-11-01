<?php

return [
    'empty' => [
        'title' => 'Keine Medien oder Ordner gefunden',
    ],
    'folders' => [
        'title' => 'Medienverwaltung',
        'single' => 'Ordner',
        'columns' => [
            'name' => 'Name',
            'collection' => 'Collection',
            'description' => 'Beschreibung',
            'is_public' => 'Öffentlich',
            'has_user_access' => 'Für ausgewählte Nutzer',
            'users' => 'Nutzer',
            'icon' => 'Icon',
            'color' => 'Farbe',
            'is_protected' => 'Passwortgeschützt',
            'password' => 'Passwort',
            'password_confirmation' => 'Passwort bestätigen',
        ],
        'filters' => [
            'all_folders' => 'Alle Ordner',
            'protected_only' => 'Nur geschützte',
            'public_only' => 'Nur öffentliche',
            'created_from' => 'Erstellt ab',
            'created_until' => 'Erstellt bis',
        ],
        'group' => 'Inhalte',
    ],
    'media' => [
        'title' => 'Medien',
        'single' => 'Medium',
        'columns' => [
            'image' => 'Bild',
            'model' => 'Model',
            'collection_name' => 'Collection',
            'size' => 'Größe',
            'order_column' => 'Reihenfolge',
        ],
        'filters' => [
            'size_from' => 'Größe ab (KB)',
            'size_to' => 'Größe bis (KB)',
            'created_from' => 'Erstellt ab',
            'created_until' => 'Erstellt bis',
        ],
        'actions' => [
            'sub_folder' => [
                'label' => 'Unterordner anlegen',
            ],
            'create' => [
                'label' => 'Medien hinzufügen',
                'form' => [
                    'file' => 'Datei',
                    'title' => 'Titel',
                    'description' => 'Beschreibung',
                ],
            ],
            'delete' => [
                'label' => 'Ordner löschen',
            ],
            'edit' => [
                'label' => 'Ordner bearbeiten',
            ],
        ],
        'notifications' => [
            'create-media' => 'Medien hinzugefügt',
            'delete-folder' => 'Ordner gelöscht',
            'edit-folder' => 'Ordner gespeichert',
        ],
        'meta' => [
            'model' => 'Model',
            'file-name' => 'Dateiname',
            'type' => 'Typ',
            'size' => 'Größe',
            'disk' => 'Disk',
            'url' => 'URL',
            'edit-media' => 'Medium bearbeiten',
            'delete-media' => 'Medium löschen',
        ],
    ],
    'picker' => [
        'title' => 'Medien auswählen',
        'browse' => 'Medien durchsuchen',
        'remove' => 'Entfernen',
        'select' => 'Auswählen',
        'cancel' => 'Abbrechen',
        'back' => 'Zurück',
        'search' => 'Ordner und Dateien durchsuchen …',
        'select_folder' => 'Ordner auswählen, um seine Medien zu sehen',
        'folders' => 'Ordner',
        'media_files' => 'Medien',
        'empty' => 'Keine Ordner oder Medien gefunden',
        'no_media_selected' => 'Keine Medien ausgewählt',
        'selected' => 'ausgewählt',
        'clear_all' => 'Alle entfernen',
        'confirm_remove' => 'Medium entfernen',
        'confirm_remove_message' => 'Soll dieses Medium wirklich entfernt werden?',
    ],
];
