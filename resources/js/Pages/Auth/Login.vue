<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import WaterWiseLogo from '@/Components/WaterWiseLogo.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const showPassword = ref(false);

const features = [
    { label: 'Sales', icon: 'M3 3h3l3 13h10l3-10H7M10 10h8M10 20a1 1 0 1 0 0 .01M18 20a1 1 0 1 0 0 .01' },
    { label: 'Orders', icon: 'M9 4H5v18h14V4h-4M9 2h6v5H9zM8 11h8M8 15h8M8 19h5' },
    { label: 'Balances', icon: 'M20 8V5H4v15h16v-5M4 5l12-3 2 3M15 10h7v6h-7zM18 13h.01' },
    { label: 'Customers', icon: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87M15 3.13a4 4 0 0 1 0 7.75M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z' },
    { label: 'Reports', icon: 'M3 22h19M5 17h3V9H5zM12 17h3V5h-3zM19 17h3V1h-3z' },
];

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
            showPassword.value = false;
        },
    });
};
</script>

<template>
    <div class="login-page">
        <Head title="Log in" />

        <main class="login-content">
            <section class="login-intro" aria-labelledby="intro-heading">
                <WaterWiseLogo />

                <h1 id="intro-heading" class="login-heading">
                    <span class="xl:block">Smarter Management</span>{{ ' ' }}
                    <span class="xl:block">for Water Refilling</span>{{ ' ' }}
                    <span class="text-[#007bff] xl:block">Stations</span>
                </h1>

                <p class="login-description">
                    Manage sales, orders, balances,<br class="hidden xl:block" />
                    customers, and reports&mdash;all in one system.
                </p>

                <ul class="login-features" aria-label="Everything your station needs">
                    <li
                        v-for="feature in features"
                        :key="feature.label"
                        class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-[#dfedfc] bg-white/85 px-2 py-4 text-center shadow-[0_5px_12px_rgba(28,111,199,0.05)]"
                    >
                        <svg
                            class="h-10 w-10 text-[#007bff]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path :d="feature.icon" />
                        </svg>
                        <span class="text-sm font-semibold sm:text-base xl:text-lg">
                            {{ feature.label }}
                        </span>
                    </li>
                </ul>
            </section>

            <section class="login-card" aria-labelledby="login-heading">
                <header class="flex flex-col items-center text-center">
                    <WaterWiseLogo compact />
                    <h2 id="login-heading" class="mt-7 text-[30px] font-bold leading-tight tracking-tight">
                        Welcome Back!
                    </h2>
                    <p class="mt-2 text-base leading-relaxed text-[#597193] sm:text-lg">
                        Sign in to your account to continue.
                    </p>
                </header>

                <div
                    v-if="status"
                    class="mt-6 rounded-xl border border-green-200 bg-green-50 p-3 text-sm font-medium text-green-700"
                    role="status"
                >
                    {{ status }}
                </div>

                <form class="mt-9 flex flex-col gap-5" :aria-busy="form.processing" @submit.prevent="submit">
                    <div>
                        <InputLabel for="email" value="Email Address" class="sr-only" />
                        <div class="relative">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="1.5" />
                                <path d="m3 6 9 7 9-7" />
                            </svg>
                            <TextInput
                                id="email"
                                v-model="form.email"
                                name="email"
                                type="email"
                                class="login-input"
                                placeholder="Email Address"
                                :aria-invalid="!!form.errors.email"
                                :aria-describedby="form.errors.email ? 'email-error' : undefined"
                                required
                                autofocus
                                autocomplete="username"
                            />
                        </div>
                        <InputError id="email-error" class="mt-2" :message="form.errors.email" aria-live="polite" />
                    </div>

                    <div>
                        <InputLabel for="password" value="Password" class="sr-only" />
                        <div class="relative">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="5" y="10" width="14" height="11" rx="1.5" />
                                <path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" />
                            </svg>
                            <TextInput
                                id="password"
                                v-model="form.password"
                                name="password"
                                :type="showPassword ? 'text' : 'password'"
                                class="login-input login-input--password"
                                placeholder="Password"
                                :aria-invalid="!!form.errors.password"
                                :aria-describedby="form.errors.password ? 'password-error' : undefined"
                                required
                                autocomplete="current-password"
                            />
                            <button
                                type="button"
                                class="password-toggle"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                :aria-pressed="showPassword"
                                aria-controls="password"
                                @click="showPassword = !showPassword"
                            >
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <template v-if="showPassword">
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </template>
                                    <template v-else>
                                        <path d="m3 3 18 18M10.6 5.1A11 11 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3 3.9M6.4 6.4A18 18 0 0 0 2 12s3.5 7 10 7a11 11 0 0 0 5.6-1.6M10 10a3 3 0 0 0 4 4" />
                                    </template>
                                </svg>
                            </button>
                        </div>
                        <InputError id="password-error" class="mt-2" :message="form.errors.password" aria-live="polite" />
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 py-2 text-sm sm:text-base">
                        <label class="flex cursor-pointer items-center gap-3 text-[#4f678b]">
                            <Checkbox
                                v-model:checked="form.remember"
                                name="remember"
                                class="h-[22px] w-[22px] rounded border-[#b9d4f3] text-[#007bff] focus:ring-[#007bff]"
                            />
                            <span>Remember me</span>
                        </label>

                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="rounded text-[#0075f6] transition-colors hover:text-[#005bc7] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#007bff]"
                        >
                            Forgot password?
                        </Link>
                    </div>

                    <button
                        type="submit"
                        class="flex min-h-[64px] w-full items-center justify-center gap-4 rounded-xl bg-[#0073ef] px-5 py-4 text-xl font-semibold text-white transition-colors hover:bg-[#0063d1] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#007bff] disabled:cursor-wait disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <span>{{ form.processing ? 'Logging in...' : 'Log In' }}</span>
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 12h16m-7-7 7 7-7 7" />
                        </svg>
                    </button>
                </form>

                <div class="mt-11 flex items-center gap-5 text-sm text-[#526f98]" aria-hidden="true">
                    <span class="h-px flex-1 bg-[#dce9f8]"></span>
                    <span>WaterWise POS</span>
                    <span class="h-px flex-1 bg-[#dce9f8]"></span>
                </div>
            </section>
        </main>

        <!-- <footer class="login-footer">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white text-[#0074ed]">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7Zm0 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" />
                </svg>
            </span>
            <p>Built for Water Refilling Stations in the Philippines</p>
        </footer> -->
    </div>
</template>

<style scoped>
.login-page {
    display: flex;
    min-height: 100vh;
    min-height: 100svh;
    flex-direction: column;
    background: #eaf7ff url('/item-images/bg.png') center / cover no-repeat;
    color: #08234c;
    font-family: Arial, sans-serif;
}

.login-content {
    display: grid;
    width: min(calc(100% - 9.2vw), 1570px);
    flex: 1;
    grid-template-columns: minmax(0, 1.08fr) minmax(0, 1fr);
    align-items: start;
    gap: clamp(3rem, 6vw, 6.5rem);
    margin-inline: auto;
    padding-block: clamp(3rem, 11.4vh, 6.5rem) 0.875rem;
}

.login-heading {
    margin-top: 2rem;
    font-size: clamp(2.5rem, 3.85vw, 4.25rem);
    font-weight: 700;
    letter-spacing: -0.035em;
    line-height: 1.12;
}

.login-description {
    margin-top: 1.15rem;
    color: #586d8e;
    font-size: clamp(1.125rem, 1.72vw, 1.875rem);
    font-weight: 600;
    letter-spacing: -0.025em;
    line-height: 1.4;
}

.login-features {
    display: grid;
    max-width: 610px;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: clamp(0.5rem, 1vw, 1.125rem);
    margin-top: 1.6rem;
}

.login-card {
    width: 100%;
    max-width: 500px;
    margin-top: 1.75rem;
    padding: clamp(2rem, 3vw, 3.25rem);
    padding-bottom: 2.5rem;
    border: 1px solid rgb(255 255 255 / 90%);
    border-radius: 20px;
    background: rgb(255 255 255 / 97%);
    box-shadow: 0 12px 36px rgb(36 117 195 / 12%);
}

.input-icon {
    position: absolute;
    top: 50%;
    left: 1rem;
    width: 24px;
    height: 24px;
    transform: translateY(-50%);
    color: #6882a5;
    pointer-events: none;
}

.login-input {
    width: 100%;
    height: 60px;
    padding-inline: 3.75rem 1rem;
    border: 1px solid #d7e8fc;
    border-radius: 12px;
    background: rgb(239 247 255 / 80%);
    color: #08234c;
    font-size: 18px;
    box-shadow: none;
    transition: border-color 150ms, box-shadow 150ms;
}

.login-input::placeholder {
    color: #7890b0;
    opacity: 1;
}

.login-input:focus {
    border-color: #007bff;
    outline: none;
    box-shadow: 0 0 0 3px rgb(0 123 255 / 12%);
}

.login-input[aria-invalid='true'] {
    border-color: #dc2626;
}

.login-input--password {
    padding-right: 3.5rem;
}

.password-toggle {
    position: absolute;
    top: 50%;
    right: 0.5rem;
    display: flex;
    width: 44px;
    height: 44px;
    align-items: center;
    justify-content: center;
    transform: translateY(-50%);
    border-radius: 8px;
    color: #6882a5;
}

.password-toggle:hover {
    color: #0073ef;
}

.password-toggle:focus-visible {
    outline: 2px solid #007bff;
    outline-offset: -2px;
}

.login-footer {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.75rem 3.7vw 2.75rem;
    color: white;
    font-size: clamp(1rem, 1.4vw, 1.5rem);
    font-weight: 600;
    text-shadow: 0 1px 3px rgb(0 68 146 / 30%);
}

@media (min-width: 1024px) and (max-height: 800px) {
    .login-content {
        padding-top: 2rem;
    }

    .login-card {
        margin-top: 0.75rem;
        padding: 2rem;
    }

    .login-footer {
        padding-bottom: 1.5rem;
    }
}

@media (max-width: 1023px) {
    .login-content {
        width: min(calc(100% - 3rem), 680px);
        grid-template-columns: minmax(0, 1fr);
        gap: 2.5rem;
        padding-block: 3rem 2rem;
    }

    .login-intro {
        text-align: center;
    }

    .login-heading {
        margin-top: 1.5rem;
        font-size: clamp(2rem, 5vw, 3rem);
    }

    .login-description {
        max-width: 550px;
        margin-inline: auto;
        font-size: 1.125rem;
    }

    .login-features {
        margin-inline: auto;
    }

    .login-card {
        justify-self: center;
        margin-top: 0;
    }

    .login-footer {
        justify-content: center;
        padding: 1.5rem;
        background: rgb(0 103 214 / 90%);
    }
}

@media (max-width: 479px) {
    .login-content {
        width: calc(100% - 2rem);
        gap: 1.75rem;
        padding-top: 2rem;
    }

    .login-heading {
        font-size: 1.75rem;
    }

    .login-features {
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 0.625rem;
    }

    .login-features > li {
        grid-column: span 2;
    }

    .login-features > li:nth-child(4) {
        grid-column: 2 / span 2;
    }

    .login-card {
        padding: 1.75rem 1.25rem;
        border-radius: 18px;
    }

    .login-footer {
        font-size: 0.875rem;
    }
}
</style>
