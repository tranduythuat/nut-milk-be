<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bảng quy đổi đơn vị
    |--------------------------------------------------------------------------
    |
    | Mỗi đơn vị gồm: type (mass|volume|count) và factor quy đổi VỀ ĐƠN VỊ
    | CƠ SỞ (base unit) của type đó (factor = 1 nghĩa là chính nó là base).
    |
    | Base unit là đơn vị BẮT BUỘC dùng để lưu raw_materials.stock,
    | reserved_quantity, min_stock và product_variant_ingredients.quantity
    | trong database. Mọi input dùng đơn vị khác sẽ bị quy đổi về base
    | TRƯỚC khi ghi xuống DB — không bao giờ lưu lẫn lộn đơn vị.
    |
    */
    'definitions' => [
        'g'   => ['type' => 'mass',   'factor' => 1],
        'kg'  => ['type' => 'mass',   'factor' => 1000],
        'ml'  => ['type' => 'volume', 'factor' => 1],
        'l'   => ['type' => 'volume', 'factor' => 1000],
        'cai' => ['type' => 'count',  'factor' => 1],
    ],

    // Đơn vị cơ sở cho mỗi type — đây là unit BẮT BUỘC khi tạo RawMaterial mới.
    'base_units' => [
        'mass'   => 'g',
        'volume' => 'ml',
        'count'  => 'cai',
    ],

];
