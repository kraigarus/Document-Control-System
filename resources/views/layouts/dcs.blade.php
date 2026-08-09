<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('images/cspc.webp') }}" type="image/webp">

    <script>
        window.proccedto = window.proccedto || function(url) {
            window.location.href = url;
        };
        window.showButtonSection = window.showButtonSection || function(button_target) {
            const navigation = document.getElementById('navigation');
            const pla = document.getElementById(button_target);
            if (navigation && pla && navigation.classList.contains('imup')) {
                pla.classList.toggle('show');
            }
        };
    </script>

    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body, button, input, select, textarea, a, span, div, h1, h2, h3, h4, h5, h6, label {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }
        ::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
        * {
            -ms-overflow-style: none !important;
            scrollbar-width: none !important;
        }
        @media screen and (min-width: 769px) {
            :root {
                zoom: clamp(0.72, calc(100vw / 1920), 1);
            }
        }
    </style>

    @vite([
        'resources/css/app.css',
        'resources/css/portal.css',
        'resources/css/dcs/sidebar.css',
        'resources/css/dcs/header.css',
        'resources/css/dcs/dashboard.css',
        'resources/css/dcs/register.css',
        'resources/css/dcs/update.css',
        'resources/css/dcs/edit.css',
        'resources/css/dcs/history.css',
        'resources/css/dcs/reports.css',
        'resources/css/dcs/stamping.css',
        'resources/css/dcs/database.css',
        'resources/css/dcs/settings.css',
    ])

    <title>CSPC - Document Control System</title>

    @stack('styles')
    @livewireStyles
</head>
<body>
    <header>
        <div class="cspc-logo">
            <img class="ico" src="{{ asset('images/cspc.png') }}" alt="CSPC">
        </div>
        <div class="label-container">
            <span class="subtitle">Camarines Sur Polytechnic Colleges</span>
            <span class="title">Document Control System</span>
        </div>
        <span class="office_name">
            {{ auth()->user()?->email ?? 'Document Control' }}
        </span>
    </header>

    <section>
        <div class="navigation imup" id="navigation">
            <div class="nav">
                <div class="nav-list-container" onclick="resetNavProperties()">
                    <x-nav.dcs.index />
                </div>
                <div class="account-container">
                    <div class="account-label">
                        <span class="account-email">{{ auth()->user()?->email }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div id="article-container" class="article-container imdown">
            {{ $slot }}
        </div>
    </section>

    @stack('scripts')
    @livewireScripts

    <script>
        document.addEventListener('submit', () => {
            window.submittedForm = true;
        });

        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('request', ({ component, commit, respond, succeed, fail }) => {
                succeed(({ response }) => {
                    if (window.submittedForm) {
                        setTimeout(() => {
                            const firstError = document.querySelector('.error-msg, .beta-error');
                            if (firstError) {
                                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                const input = firstError.closest('.form-col, .beta-form-group, .beta-form-group-full')?.querySelector('input, select, textarea');
                                if (input) {
                                    input.focus();
                                }
                            }
                            window.submittedForm = false;
                        }, 100);
                    }
                });
            });
        });
    </script>
</body>
</html>