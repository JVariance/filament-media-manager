<?php

return [
    'empty' => [
        'title' => 'لا يوجد وسائط أو مجلدات',
    ],
    'folders' => [
        'title' => 'مدير الوسائط',
        'single' => 'مجلد',
        'columns' => [
            'name' => 'الاسم',
            'collection' => 'المجموعة',
            'description' => 'الوصف',
            'icon' => 'الايقونة',
            'color' => 'اللون',
            'custom_color' => 'لون مخصص',
            'is_protected' => 'محمي بكلمة مرور',
            'password' => 'كلمة المرور',
            'password_confirmation' => 'تأكيد كلمة المرور',
        ],
        'filters' => [
            'all_folders' => 'كل المجلدات',
            'protected_only' => 'المحمية فقط',
            'public_only' => 'العامة فقط',
            'created_from' => 'تم الإنشاء من',
            'created_until' => 'تم الإنشاء حتى',
        ],
        'group' => 'المحتوي',
        'actions' => [
            'move' => [
                'label' => 'نقل المجلد',
                'target' => 'نقل إلى',
                'top_level' => 'المستوى الأعلى',
            ],
        ],
        'notifications' => [
            'moved' => 'تم نقل :folder إلى :target',
            'moved_to_top_level' => 'تم نقل :folder إلى المستوى الأعلى',
        ],
    ],
    'media' => [
        'title' => 'الوسائط',
        'single' => 'وسائط',
        'columns' => [
            'image' => 'الصورة',
            'model' => 'النموذج',
            'collection_name' => 'اسم المجموعة',
            'size' => 'الججم',
            'order_column' => 'عمود الترتيب',
        ],
        'filters' => [
            'size_from' => 'الحجم من (كيلوبايت)',
            'size_to' => 'الحجم إلى (كيلوبايت)',
            'created_from' => 'تم الإنشاء من',
            'created_until' => 'تم الإنشاء حتى',
        ],
        'actions' => [
            'sub_folder' => [
                'label' => 'إنشاء مجلد فرعي',
            ],
            'create' => [
                'label' => 'إضافة وسائط',
                'form' => [
                    'file' => 'الملف',
                    'title' => 'العنوان',
                    'description' => 'الوصف',
                ],
            ],
            'delete' => [
                'label' => 'حذف المجلد',
            ],
            'edit' => [
                'label' => 'تعديل المجلد',
            ],
            'move' => [
                'label' => 'نقل إلى مجلد',
                'folder' => 'المجلد',
            ],
        ],
        'notifications' => [
            'create-media' => 'تم إنشاء الوسائط بنجاح',
            'delete-folder' => 'تم حذف المجلد بنجاح',
            'edit-folder' => 'تم تعديل المجلد بنجاح',
            'moved' => '{0} لم يتم نقل أي وسائط|{1} تم نقل وسيط واحد إلى :folder|[2,*] تم نقل :count وسائط إلى :folder',
        ],
        'meta' => [
            'model' => 'نموذج',
            'file-name' => 'اسم الملف',
            'type' => 'نوع',
            'size' => 'حجم',
            'disk' => 'قرص',
            'url' => 'رابط',
            'delete-media' => 'حذف الوسائط',
        ],
    ],
    'picker' => [
        'title' => 'اختر الوسائط',
        'browse' => 'تصفح الوسائط',
        'remove' => 'إزالة',
        'select' => 'اختيار',
        'cancel' => 'إلغاء',
        'back' => 'رجوع',
        'breadcrumbs' => 'مسار المجلد',
        'search' => 'بحث في المجلدات والملفات...',
        'select_folder' => 'اختر مجلد لتصفح ملفات الوسائط',
        'folders' => 'المجلدات',
        'media_files' => 'ملفات الوسائط',
        'empty' => 'لا توجد مجلدات أو ملفات وسائط',
        'no_media_selected' => 'لم يتم اختيار وسائط',
        'selected' => 'محدد',
        'clear_all' => 'مسح الكل',
        'confirm_remove' => 'إزالة الوسائط',
        'confirm_remove_message' => 'هل أنت متأكد من إزالة هذا العنصر؟',
        'create_folder' => 'مجلد جديد',
        'folder_created' => 'تم إنشاء المجلد',
    ],
];
