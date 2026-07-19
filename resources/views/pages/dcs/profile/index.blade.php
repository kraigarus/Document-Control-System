<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/images/logo.png" type="image/png">
    <title>My Profile - Document Control System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite([
        'resources/css/dcs/settings.css',
        'resources/css/dcs/profile.css',
        'resources/js/dcs/profile.js'
    ])
</head>
<body>

@include('partials.header')
@include('partials.sidebar')
@include('partials.inactivity-modal')

<main class="settings-main">
    <div class="settings-header">
        <div class="welcome-text">
            <p class="header-greeting">Account</p>
            <h1 class="page-title">My Profile</h1>
        </div>
    </div>

    <!-- ══════════════ IDENTITY CARD ══════════════ -->
    <div class="profile-card">
        <div class="profile-avatar-wrap">
            <div class="profile-avatar" id="profileAvatarPreview"
                 style="{{ $user->profile_photo ? 'background-image:url(' . Storage::url($user->profile_photo) . ')' : '' }}">
                @unless($user->profile_photo)
                    <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                @endunless
            </div>
            <label class="profile-avatar-edit" title="Change photo">
                <i class="fa-solid fa-camera"></i>
                <input type="file" id="photoInput" accept="image/png,image/jpeg,image/webp" hidden>
            </label>
        </div>
        <div class="profile-identity">
            <h2>{{ $user->name }}</h2>
            <p>{{ $user->email }}</p>
        </div>
        @if($user->profile_photo)
            <button class="st-btn st-btn-ghost profile-remove-photo" id="removePhotoBtn">
                <i class="fa-solid fa-trash"></i> Remove Photo
            </button>
        @endif
    </div>

    <div class="settings-tabs">
        <button class="tab-btn active" data-tab="info">
            <i class="fa-solid fa-id-card"></i> Profile Info
        </button>
        <button class="tab-btn" data-tab="security">
            <i class="fa-solid fa-lock"></i> Security
        </button>
    </div>

    <!-- ══════════════ PROFILE INFO ══════════════ -->
    <section class="tab-panel active" id="panel-info">
        <div class="profile-form-card">
            <form id="infoForm">
                <div class="st-field">
                    <label class="st-label">Full Name</label>
                    <input type="text" id="nameInput" class="st-input" value="{{ old('name', $user->name) }}">
                </div>
                <div class="st-field">
                    <label class="st-label">Email Address</label>
                    <input type="email" id="emailInput" class="st-input" value="{{ old('email', $user->email) }}">
                </div>
                <div class="st-actions-row">
                    <button type="submit" class="st-btn st-btn-primary">
                        <i class="fa-solid fa-check"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- ══════════════ SECURITY ══════════════ -->
    <section class="tab-panel" id="panel-security">
        <div class="profile-form-card">
            <form id="passwordForm">
                <div class="st-field">
                    <label class="st-label">Current Password</label>
                    <input type="password" id="currentPasswordInput" class="st-input" autocomplete="current-password">
                </div>
                <div class="st-field">
                    <label class="st-label">New Password</label>
                    <input type="password" id="newPasswordInput" class="st-input" autocomplete="new-password">
                </div>
                <div class="st-field">
                    <label class="st-label">Confirm New Password</label>
                    <input type="password" id="newPasswordConfirmInput" class="st-input" autocomplete="new-password">
                </div>
                <div class="st-actions-row">
                    <button type="submit" class="st-btn st-btn-primary">
                        <i class="fa-solid fa-key"></i> Update Password
                    </button>
                </div>
            </form>
        </div>
    </section>

</main>

<!-- Toast -->
<div id="settingsToast" class="settings-toast"></div>

</body>
</html>