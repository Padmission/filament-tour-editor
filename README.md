# Filament Tour Editor

A visual tour builder for Filament v5 that lets you create guided onboarding tours with a point-and-click interface — no code required. Tours are stored in the database and managed through a built-in admin resource with import/export, cloning, and drag-and-drop ordering.

Built on top of [jibaymcs/filament-tour](https://github.com/JibayMcs/filament-tour) and [Driver.js](https://driverjs.com/) for the frontend rendering.

## Features

- **Visual Tour Builder** — slide-over panel accessible from the user menu on any page
- **Element Picker** — hover-highlight and click to select target elements with smart CSS selector generation
- **Database-Backed Tours** — tours stored in a `tours` table, managed via a Filament resource
- **LaunchTourAction** — drop-in Filament action that shows a "Take Tour" button on any page with an active tour
- **Preview Mode** — preview tours in-place before saving
- **Modal & Element Steps** — steps can target a specific element or display as centered modals
- **Reorderable Steps** — drag-and-drop step ordering in both the builder and resource table
- **Clone Tours** — duplicate existing tours for quick iteration
- **Icon & Color Support** — optional Heroicon and color per step
- **Configurable Button Labels** — customize Next, Previous, and Done text per tour
- **Authorization** — policy-based access control; builder visibility configurable via plugin closure
- **Trait-Based Tours Preserved** — existing `HasTour` trait tours continue to work alongside database tours

## Requirements

- PHP 8.2+
- Filament v5
- [jibaymcs/filament-tour](https://github.com/JibayMcs/filament-tour) ^5.0

## Installation

Add the repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:Padmission/filament-tour-editor.git"
        }
    ]
}
```

Then install via Composer:

```bash
composer require padmission/filament-tour-editor:dev-main
```

Run migrations:

```bash
php artisan migrate
```

## Setup

Register the plugin in your panel provider:

```php
use Padmission\FilamentTourEditor\FilamentTourEditorPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentTourEditorPlugin::make()
                ->navigationGroup('Support')
                ->navigationLabel('Manage Tours'),
        ]);
}
```

## Configuration

### Plugin Options

```php
FilamentTourEditorPlugin::make()
    // Sidebar navigation group for the Tours resource
    ->navigationGroup('Support')

    // Sidebar navigation label
    ->navigationLabel('Manage Tours')

    // Disable the visual builder if you only need the admin resource (default: true)
    ->enableBuilder(true)

    // Disable the admin resource if you only need the builder (default: true)
    ->enableResource(true);
```

### Authorization

The package registers a default `TourPolicy` that permits all actions for any authenticated user. To customize authorization, publish the policy and modify it:

```bash
php artisan vendor:publish --tag=filament-tour-editor-policies
```

This publishes `TourPolicy.php` to `app/Policies/`, where you can restrict access as needed:

```php
namespace App\Policies;

use App\Models\User;
use Padmission\FilamentTourEditor\Models\Tour;

class TourPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    // ... customize other methods: view, update, delete, deleteAny, import, export
}
```

The package automatically detects `App\Policies\TourPolicy` and uses it over the default.

**Available policy methods:**

| Method | Controls |
|--------|----------|
| `viewAny` | Listing tours |
| `view` | Viewing a single tour |
| `create` | Creating tours |
| `update` | Editing tours |
| `delete` | Deleting a tour |
| `deleteAny` | Bulk deleting tours |
| `import` | Importing tours via CSV |
| `export` | Exporting tours to CSV |

## Usage

### Building Tours (Visual Builder)

1. Navigate to any page in your Filament panel
2. Click your avatar in the top-right corner
3. Select **Build Tour**
4. Add steps with titles and descriptions
5. Click **Pick target** to select a page element for each step (or leave empty for a centered modal step)
6. Click **Save** to persist, or **Preview** to test first

### Managing Tours (Admin Resource)

The **Manage Tours** page (under your configured navigation group) provides:

- Table listing all tours with name, route, step count, and active status
- Inline editing via slide-over modals
- Clone action for duplicating tours
- Drag-and-drop reordering via `sort_order`
- Bulk delete

### LaunchTourAction

Add a "Take Tour" button to any Filament page. The action automatically finds the active tour for the current route and dispatches it. It hides itself when no tour exists for the page.

```php
use Padmission\FilamentTourEditor\Actions\LaunchTourAction;

protected function getHeaderActions(): array
{
    return [
        LaunchTourAction::make(),
    ];
}
```

No configuration needed — it resolves the tour from the current route automatically.

### Cloning Tours

The Clone action (available in each row's action menu) duplicates a tour for quick iteration:

- The cloned tour's name is appended with " Copy" (e.g., "Onboarding" → "Onboarding Copy")
- `json_config.id` is re-generated from the new name, so the clone is treated as a separate tour for localStorage tracking — users who completed the original will still see the clone
- `is_active` is set to `false` so the clone doesn't immediately appear to users
- All steps, button labels, and configuration are preserved from the original
- The clone opens in a slide-over editor so you can rename or modify it before saving

### Import / Export

The Manage Tours page includes an **Import / Export** action group for bulk tour management.

**Exporting** downloads all tours as a CSV file including name, description, route, JSON configuration, active status, and sort order.

**Importing** supports two modes via a radio prompt:

- **Add to existing tours** (default) — imported tours are created alongside existing ones
- **Replace all existing tours** — all existing tours are deleted before importing

The CSV format matches the export format, so you can export from one environment and import into another. A downloadable example CSV is available in the import modal.

**Tour identity and localStorage:** Tour completion is tracked in the browser via `localStorage` using the tour's `json_config.id` (or `Str::slug(name)` as fallback). When using "Replace" mode, as long as the imported tours have the same names or `json_config.id` values, users won't be re-prompted for tours they've already completed.

### Trait-Based Tours (Code-Defined)

This package doesn't replace the `HasTour` trait from [filament-tour](https://github.com/JibayMcs/filament-tour). Tours defined in code via the trait continue to work alongside database-managed tours. When a page loads, both sources are merged and presented to the user.

Use code-defined tours when you want tours version-controlled with your codebase, and database tours when you want non-developers to create and manage tours without deploying code.

### Multiple Tours on the Same Route

Multiple active tours can exist on the same route. When a page loads with more than one matching tour, they are presented **sequentially** — the first tour plays, and when the user completes it (clicks "Done" on the last step), the next tour starts automatically.

The `sort_order` column controls the sequence. Tours with a lower sort order play first. You can reorder tours via drag-and-drop on the Manage Tours page.

Each tour's completion is tracked independently in `localStorage`. If a user has already completed a tour, it is skipped and the next unseen tour plays instead.

### Resetting Tour History

Users can click **Reset Tours** from the user menu to clear their dismissed-tour history and replay all tours.

## Tour Model

Tours are stored in the `tours` table with the following structure:

| Column | Type | Description |
|--------|------|-------------|
| `name` | string | Display name |
| `description` | text (nullable) | Internal description |
| `route` | string | Named route or URL path where the tour appears |
| `json_config` | json | Tour ID, steps array, and button config |
| `is_active` | boolean | Whether the tour is shown to users |
| `sort_order` | integer | Display/load order |

### Scopes

```php
Tour::active()->get();           // Only active tours
Tour::forRoute('/dashboard')->get(); // Tours for a specific route
```

## How It Works

The package overrides the base `filament-tour-widget` Livewire component to inject database-stored tours alongside any trait-defined tours. When a page loads:

1. The widget collects tours from `HasTour` traits (existing behavior)
2. It then loads active database tours matching the current route
3. All tours are dispatched to the Driver.js frontend for rendering

The visual builder uses a separate `TourBuilder` Livewire component rendered at `BODY_END` that provides the slide-over form and element picker overlay.
