# 🔍 Laravel Searchable Trait

A flexible and developer-friendly trait for Laravel Eloquent models that enables **smart, customizable search functionality** across model columns and relationships. Perfect for building dynamic filters and admin panels.

---

## 📦 Installation

```
composer require youssefreda/searchable:@dev
```

> Note: This version uses `@dev` stability flag — make sure your project allows it in `composer.json` or add:
>

---

## 🚀 Features

- 🔎 Search directly on model fields
- 🔗 Supports searching across relationships (`belongsTo`, `hasMany`, etc.)
- 🔢 Smart numeric vs. string search
- 🌐 Handles Arabic text search using `utf8mb4`
- ⚙️ Define custom labels, types, and visibility
- 📥 Auto-generate dropdown options for searchable fields

---

## 🛠️ Usage

### 1. Use the Trait in Your Model

```php
use Searchable\Traits\Searchable;

class Apartment extends Model
{
    use Searchable;
}
```

---

### 2. Define `$searchable` Property

```php
protected static $searchable = [
    'client_name' => 'Client Name',
    'cost' => 'Cost',
    'user' => [
        'label' => 'User',
        'relation' => 'user',
        'field' => 'full_name'
    ]
];
```

#### Field Configuration Options:

| Key       | Type   | Description                                           |
|-----------|--------|-------------------------------------------------------|
| `label`   | string | (Optional) Display label for UI dropdown              |
| `relation`| string | (Optional) Relation name (`belongsTo`, etc.)          |
| `field`   | string | Column to search within the relation                  |
| `type`    | string | `'number'` to enforce numeric comparison              |

---

## 🔍 Search Examples

### Global Search

```php
Apartment::search('Ahmed')->get();
```

### Column-Specific Search

```php
Apartment::search('Ahmed', 'client_name')->get();
```

---

## 🎛️ Generating Dropdown Options

Use `getSearchColumnOptions()` to generate `['key' => 'label']` pairs:

```php
$options = Apartment::getSearchColumnOptions();
```

Output:

```php
[
    'client_name' => 'Client Name',
    'cost' => 'Cost',
    'user' => 'User'
]
```
### Blade Integration Example

```blade
@php
    $columns = \App\Models\Apartment::getSearchColumnOptions();
@endphp

<select name="column" class="form-select">
    <option value="all">{{ __('All Fields') }}</option>
    @foreach($columns as $value => $label)
        <option value="{{ $value }}" {{ request('column') === $value ? 'selected' : '' }}>
            {{ __($label) }}
        </option>
    @endforeach
</select>
```

> ✅ This snippet will allow the user to choose which column to search in. The list is auto-generated from the model's `$searchable` property.
---

## 🧪 Example Model: Apartment

```php
use App\Traits\Searchable;

class Apartment extends Model
{
    use Searchable;

    protected static $searchable = [
        'client_name' => 'Client Name',
        'floor' => 'Floor',
        'commission' => 'Commission',
        'user' => [
            'label' => 'User',
            'relation' => 'user',
            'field' => 'full_name'
        ],
        'project' => [
            'label' => 'Project',
            'relation' => 'project',
            'field' => 'name'
        ]
    ];

    public function user()    { return $this->belongsTo(User::class); }
    public function project() { return $this->belongsTo(Project::class); }
}
```

---

## ⚙️ How It Works

| Type     | Logic                                                      |
|----------|------------------------------------------------------------|
| String   | `LIKE %term%`                                              |
| Numeric  | `=` + `ABS(ROUND(...)) < 0.0001` for float-safe comparison |
| Relation | Uses `orWhereHas` on the defined `relation.field`          |
| Arabic   | Automatically converts field using `utf8mb4` when needed   |

---

## 🧠 Smart Numeric Detection

You can either:

- Explicitly define the type using `'type' => 'number'`
- Or let it auto-detect based on field name patterns (e.g., `cost`, `amount`, `price`, etc.)

---

## 📄 License

MIT © [Youssef Reda](https://github.com/youssefreda4)

---

## 🤝 Contribute

Pull Requests and Issues are welcome!