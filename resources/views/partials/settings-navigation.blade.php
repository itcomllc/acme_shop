<!-- Settings Navigation -->
<div class="mb-6">
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8">
            <a href="{{ route('settings.profile') }}" 
               class="{{ request()->routeIs('settings.profile') ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }} whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                {{ __('Profile') }}
            </a>
            
            <a href="{{ route('settings.password') }}" 
               class="{{ request()->routeIs('settings.password') ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }} whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                {{ __('Password') }}
            </a>
            
            <a href="{{ route('settings.appearance') }}" 
               class="{{ request()->routeIs('settings.appearance') ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }} whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                {{ __('Appearance') }}
            </a>

            @auth
                @if(Auth::user()->activeSubscription)
                    <a href="{{ route('ssl.eab.index') }}" 
                       class="{{ request()->routeIs('ssl.eab.*') ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }} whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-200">
                        {{ __('ACME Settings') }}
                    </a>
                @endif
            @endauth
        </nav>
    </div>
</div>