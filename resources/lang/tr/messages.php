<?php

return [
    'empty' => [
        'title' => 'Medya veya Klasör Bulunamadı',
    ],
    'folders' => [
        'title' => 'Medya Yöneticisi',
        'single' => 'Klasör',
        'columns' => [
            'name' => 'Ad',
            'collection' => 'Koleksiyon',
            'description' => 'Açıklama',
            'is_public' => 'Herkese Açık mı',
            'has_user_access' => 'Kullanıcı Erişimi Var mı',
            'users' => 'Kullanıcılar',
            'icon' => 'Simge',
            'color' => 'Renk',
            'custom_color' => 'Özel renk',
            'is_protected' => 'Korumalı mı',
            'password' => 'Parola',
            'password_confirmation' => 'Parola Onayı',
        ],
        'filters' => [
            'all_folders' => 'Tüm klasörler',
            'protected_only' => 'Yalnızca korumalı',
            'public_only' => 'Yalnızca genel',
            'created_from' => 'Oluşturma tarihi (başlangıç)',
            'created_until' => 'Oluşturma tarihi (bitiş)',
        ],
        'group' => 'İçerik',
        'actions' => [
            'move' => [
                'label' => 'Klasörü taşı',
                'target' => 'Taşınacak yer',
                'top_level' => 'En üst düzey',
            ],
        ],
        'notifications' => [
            'moved' => ':folder, :target klasörüne taşındı',
            'moved_to_top_level' => ':folder en üst düzeye taşındı',
        ],
    ],
    'media' => [
        'title' => 'Medya',
        'single' => 'Medya',
        'columns' => [
            'image' => 'Görsel',
            'model' => 'Model',
            'collection_name' => 'Koleksiyon Adı',
            'size' => 'Boyut',
            'order_column' => 'Sıra Sütunu',
        ],
        'filters' => [
            'size_from' => 'Boyut (başlangıç KB)',
            'size_to' => 'Boyut (bitiş KB)',
            'created_from' => 'Oluşturma tarihi (başlangıç)',
            'created_until' => 'Oluşturma tarihi (bitiş)',
        ],
        'actions' => [
            'sub_folder' => [
                'label' => 'Alt Klasör Oluştur',
            ],
            'create' => [
                'label' => 'Medya Ekle',
                'form' => [
                    'file' => 'Dosya',
                    'title' => 'Başlık',
                    'description' => 'Açıklama',
                ],
            ],
            'delete' => [
                'label' => 'Klasörü Sil',
            ],
            'edit' => [
                'label' => 'Klasörü Düzenle',
            ],
            'move' => [
                'label' => 'Klasöre taşı',
                'folder' => 'Klasör',
            ],
        ],
        'notifications' => [
            'create-media' => 'Medya başarıyla oluşturuldu',
            'delete-folder' => 'Klasör başarıyla silindi',
            'edit-folder' => 'Klasör başarıyla düzenlendi',
            'moved' => '{0} Hiçbir medya taşınmadı|{1} 1 medya :folder klasörüne taşındı|[2,*] :count medya :folder klasörüne taşındı',
        ],
        'meta' => [
            'model' => 'Model',
            'file-name' => 'Dosya Adı',
            'type' => 'Tür',
            'size' => 'Boyut',
            'disk' => 'Disk',
            'url' => 'URL',
            'delete-media' => 'Medya Sil',
        ],
        'poster' => [
            'label' => 'Kapak görseli',
            'capture' => 'Mevcut kareyi kullan',
            'upload' => 'Görsel yükle',
            'remove' => 'Kaldır',
            'none' => 'Kapak görseli yok: tarayıcı ilk kareyi gösterir.',
            'not_ready' => 'Video henüz yüklenmedi, başlatın veya biraz bekleyin.',
            'capture_failed' => 'Bu videodan kare alınamadı. Bunun yerine bir görsel yükleyin.',
            'invalid' => 'Kapak görseli JPEG, PNG veya WebP olmalıdır.',
        ],
    ],
];
