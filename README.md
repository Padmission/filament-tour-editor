# Filament Tour Editor

Visual tour builder and database-driven tour management for Filament v5, powered by [jibaymcs/filament-tour](https://github.com/JibayMcs/filament-tour) and [Driver.js](https://driverjs.com/).

Build guided onboarding tours directly on any Filament page with a point-and-click interface — no code required.

## Features

- **Visual Tour Builder** — slide-over panel accessible from the user menu on any page
- **Element Picker** — hover-highlight and click to select target elements with smart CSS selector generation
- **Database-Backed Tours** — tours stored in a `tours` table, managed via a Filament resource
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
                ->canAccessBuilder(fn ($user) => $user->isGlobalAdmin())
                ->navigationGroup('Support')
                ->navigationLabel('Manage Tours'),
        ]);
}
```

## Configuration

### Plugin Options

```php
FilamentTourEditorPlugin::make()
    // Control who sees the "Build Tour" button in the user menu
    ->canAccessBuilder(fn ($user) => $user->isGlobalAdmin())

    // Enable/disable the visual builder (default: true)
    ->enableBuilder(true)

    // Enable/disable the admin resource for CRUD management (default: true)
    ->enableResource(true)

    // Enable CSS selector mode in the base filament-tour plugin (default: false)
    ->enableCssSelector(false)

    // Sidebar navigation group for the Tours resource
    ->navigationGroup('Support')

    // Sidebar navigation label
    ->navigationLabel('Manage Tours');
```

### Authorization

The package registers a `TourPolicy` that gates write operations to `isGlobalAdmin()`:

| Action | Requirement |
|--------|-------------|
| View any (list) | Global Admin |
| View | Any authenticated user |
| Create | Global Admin |
| Update | Global Admin |
| Delete | Global Admin |

Override by publishing and modifying the policy, or by binding your own policy to the `Tour` model.

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
