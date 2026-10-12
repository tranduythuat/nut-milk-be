<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Số đợt giao tối đa cho phép chia trên 1 đơn hàng
    |--------------------------------------------------------------------------
    |
    | Khách được phép chia đơn thành nhiều đợt giao (vd để tránh sữa
    | hạt hỏng vì hạn dùng ngắn), nhưng không giới hạn sẽ tạo quá tải
    | vận hành: mỗi đợt giao kéo theo 1 Production Plan riêng, 1 lượt
    | giao hàng riêng, 1 khoản phí ship riêng (nếu có). Giới hạn này
    | chặn trường hợp cực đoan như chia 1 đơn 10 chai thành 10 đợt
    | 1-chai-1-đợt.
    |
    */
    'max_deliveries_per_order' => (int) env('CHECKOUT_MAX_DELIVERIES_PER_ORDER', 3),
    'max_quantity_per_item' => (int) env('CHECKOUT_MAX_QUANTITY_PER_ITEM', 50),

];
