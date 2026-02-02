# Implementation Checklist

Based on test results, here's a prioritized checklist of what needs to be implemented.

---

## 🔴 Critical Priority (Do First)

### 1. Admin Dashboard
**Status:** ❌ Not Implemented
**Controller:** `app/Http/Controllers/Api/V1/Admin/DashboardController.php`

```php
// Required method: index()
// Should return:
- total_orders (count)
- total_revenue (sum of completed orders)
- total_customers (count where role = customer)
- total_products (count)
```

**Tests waiting:** 6 tests in `AdminDashboardTest.php`

---

### 2. Admin Order Management
**Status:** ❌ Not Implemented
**Controller:** `app/Http/Controllers/Api/V1/Admin/AdminOrderController.php`

```php
// Required methods:
- index()        // List all orders with filters (status, user_id, search)
- show($id)      // View order details
- updateStatus() // Update order status and record in history
```

**Tests waiting:** 12 tests in `AdminOrderTest.php`

**Database tables:**
- ✅ `orders` - Already exists
- ✅ `order_status_histories` - Already exists

---

### 3. Complete Checkout Flow
**Status:** 🔄 Partial
**Controller:** `app/Http/Controllers/Api/V1/OrderController.php`

```php
// checkout() method needs:
1. Validate cart is not empty
2. Validate shipping address exists
3. Validate all items have sufficient stock
4. Create order from cart items
5. Decrease product stock
6. Clear cart
7. Create payment record
8. Return payment URL (MercadoPago integration)
```

**Tests waiting:** 4 tests in `CustomerOrderTest.php`

---

### 4. Order Cancellation
**Status:** ❌ Not Implemented
**Controller:** `app/Http/Controllers/Api/V1/OrderController.php`

```php
// cancel($id) method needs:
1. Check order belongs to authenticated user
2. Check order status is 'pending' or 'processing'
3. Update order status to 'cancelled'
4. Restore product stock
5. Record in order_status_histories
```

**Tests waiting:** 2 tests in `CustomerOrderTest.php`

---

## 🟡 High Priority (Week 1)

### 5. Admin Customer Management
**Status:** ❌ Not Implemented
**Controller:** `app/Http/Controllers/Api/V1/Admin/AdminCustomerController.php`

```php
// Required methods:
- index()    // List customers (role = customer) with search
- show($id)  // Customer details with orders_count and total_spent
```

**Tests waiting:** 10 tests in `AdminCustomerTest.php`

**Query requirements:**
- Filter: Only users with role = 'customer'
- Search: By name or email
- Include: orders_count, total_spent (from completed orders)

---

### 6. Admin Category Management
**Status:** ❌ Not Implemented
**Controller:** `app/Http/Controllers/Api/V1/Admin/AdminCategoryController.php`

```php
// Required methods:
- index()       // List all categories
- store()       // Create category (validate slug uniqueness)
- show($id)     // View category details
- update($id)   // Update category
- destroy($id)  // Delete category
```

**Tests waiting:** 8 tests in `AdminCategoryTest.php`

**Validation rules:**
```php
'name' => 'required|string|max:255',
'slug' => 'required|string|unique:categories,slug|max:255',
'description' => 'nullable|string',
```

---

### 7. Wishlist Duplicate Prevention
**Status:** ⚠️ Missing Validation
**Controller:** `app/Http/Controllers/Api/V1/WishlistController.php`

```php
// store() method needs:
1. Check if product_id already exists for user
2. Return 422 if duplicate
3. Validate product exists and is active
```

**Tests waiting:** 2 tests in `WishlistManagementTest.php`

**Validation:**
```php
$request->validate([
    'product_id' => [
        'required',
        'exists:products,id',
        Rule::unique('wishlists')->where('user_id', auth()->id())
    ]
]);
```

---

### 8. Product Image Upload
**Status:** ❌ Not Implemented
**Controller:** `app/Http/Controllers/Api/V1/Admin/AdminProductController.php`

```php
// uploadImage($id) method needs:
1. Validate image file
2. Store in public/storage/products
3. Create ProductImage record
4. Return image URL
```

```php
// deleteImage($id, $imageId) method needs:
1. Find image
2. Delete from storage
3. Delete database record
```

**Tests waiting:** 2 tests in `AdminProductTest.php`

---

## 🟢 Medium Priority (Week 2-3)

### 9. Admin Reports
**Status:** ❌ Not Implemented
**Controller:** `app/Http/Controllers/Api/V1/Admin/ReportController.php`

```php
// sales() - Sales report
Returns:
- total_sales (sum of completed orders)
- total_orders (count of completed orders)
- average_order_value
- Optional: Filter by date range

// products() - Products report
Returns:
- total_products
- active_products
- out_of_stock (stock = 0)
- low_stock (stock < 10)

// customers() - Customers report
Returns:
- total_customers
- new_customers (filter by period parameter)
```

**Tests waiting:** 10 tests in `AdminReportTest.php`

---

### 10. Admin Settings Management
**Status:** ❌ Not Implemented
**Controller:** `app/Http/Controllers/Api/V1/Admin/AdminSettingController.php`

```php
// Required methods:
- index()       // List all settings
- show($key)    // Get specific setting
- update()      // Update multiple settings
```

**Tests waiting:** 6 tests in `AdminSettingTest.php`

**Database:**
- ✅ `settings` table exists

---

### 11. Contact Message Management
**Status:** ❌ Not Implemented
**Controller:** `app/Http/Controllers/Api/V1/Admin/AdminContactController.php`

```php
// Required methods:
- index()          // List messages with status filter
- show($id)        // View message details
- reply($id)       // Add reply and set status to 'replied'
- updateStatus($id) // Update message status
```

**Tests waiting:** 9 tests in `AdminContactTest.php`

**Statuses:** pending, replied, resolved

---

## 🔵 Low Priority (Future)

### 12. Enhanced Product Validation
**Location:** Various controllers

- Stricter price validation
- Better SKU generation
- Slug auto-generation from name
- Rich text description support

---

### 13. Stock Management Refinement
**Location:** `CartController`, `OrderController`

- More edge cases in cart validation
- Stock reservation during checkout
- Stock alerts for low inventory
- Backorder support

---

### 14. Advanced Search & Filtering
**Location:** Public and admin product endpoints

- Fulltext search (when not using SQLite)
- Multi-attribute filtering
- Price range filters
- Sorting options

---

## 📋 Implementation Order Recommendation

**Day 1-2:**
1. Admin Dashboard (simple stats queries)
2. Admin Order Management (read-only first)

**Day 3-4:**
3. Order Cancellation
4. Complete Checkout Flow

**Day 5-7:**
5. Admin Customer Management
6. Admin Category Management
7. Wishlist Validation

**Week 2:**
8. Product Image Upload
9. Admin Reports

**Week 3:**
10. Settings Management
11. Contact Messages
12. Refinements and bug fixes

---

## 🧪 Testing After Implementation

After implementing each feature, run:

```bash
# Test specific feature
php artisan test tests/Feature/Admin/AdminDashboardTest.php

# Run all tests
php artisan test --testsuite=Feature

# Verify test count increases
php artisan test --compact
```

**Goal:** Get from 96 passing tests to 171 passing tests (100%)

---

## 💡 Implementation Tips

### For Controllers
1. Use dependency injection for services
2. Use Form Requests for validation
3. Use Resources for API responses
4. Return consistent JSON structure:
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message"
}
```

### For Queries
1. Use Eloquent relationships
2. Apply eager loading to avoid N+1
3. Use query scopes for reusable filters
4. Paginate list endpoints

### For Authorization
1. All admin endpoints already have middleware
2. Just implement the controller methods
3. Tests will verify permissions automatically

---

## 📚 Helpful Resources

**Existing Code to Reference:**
- `app/Http/Controllers/Api/V1/ProductController.php` - Example of filtering/search
- `app/Http/Controllers/Api/V1/AuthController.php` - Example of validation
- `app/Models/Order.php` - Relationships and scopes
- `app/Support/Authorization/RolePermissions.php` - Permission matrix

**Laravel Documentation:**
- [Controllers](https://laravel.com/docs/controllers)
- [Validation](https://laravel.com/docs/validation)
- [Eloquent Relationships](https://laravel.com/docs/eloquent-relationships)
- [API Resources](https://laravel.com/docs/eloquent-resources)

---

## ✅ Completion Criteria

You're done when:
- [ ] All 171 tests pass
- [ ] No ❌ FAIL in `ENDPOINT_TEST_RESULTS.md`
- [ ] Admin panel is fully functional
- [ ] Customers can complete checkout
- [ ] Orders can be managed by admins

**Current:** 96/171 tests passing (56%)
**Target:** 171/171 tests passing (100%)

---

**Good luck with the implementation! 🚀**

The tests are ready and waiting for your code. Each passing test is a step closer to a complete e-commerce platform.
