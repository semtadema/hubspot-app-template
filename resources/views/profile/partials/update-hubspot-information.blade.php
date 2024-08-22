<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Hubspot information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update your account's hubspot information.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        @if(isset(\Illuminate\Support\Facades\Auth::user()->hs_portal_id))
            <p>
                Portal id: {{Auth::user()->hs_portal_id}}
            </p>
        @endif
        <div class="flex items-center gap-4">
            <x-primary-button>
                <a href="{{env("HUBSPOT_OAUTH_URL")}}">
                    @if(isset(\Illuminate\Support\Facades\Auth::user()->hubspot_portal_id))
                        {{ __('Reconnect to Hubspot') }}
                        @else
                        {{ __('Connect to Hubspot') }}
                    @endif
                </a>
            </x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
