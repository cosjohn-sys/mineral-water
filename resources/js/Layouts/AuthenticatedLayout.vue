<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AppSidebar from '@/Components/AppSidebar.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { ref } from 'vue';

const mobileNavigation = ref(null);
</script>

<template>
    <div class="workspace-backdrop">
        <a href="#main-content" class="sr-only z-[60] rounded-lg bg-white px-4 py-3 font-medium text-blue-700 focus:not-sr-only focus:fixed focus:left-4 focus:top-4 print:hidden">Skip to content</a>

        <div class="workspace-frame">
            <aside class="desktop-sidebar hidden lg:block print:hidden">
                <AppSidebar />
            </aside>

            <dialog ref="mobileNavigation" class="mobile-navigation print:hidden" aria-label="Navigation menu" @click="($event.target === mobileNavigation) && mobileNavigation.close()">
                <button type="button" aria-label="Close navigation" class="absolute right-2 top-2 z-10 rounded-lg p-2 text-white hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white" @click="mobileNavigation.close()">
                    <AppIcon name="close" class="h-5 w-5" />
                </button>
                <AppSidebar @navigate="mobileNavigation.close()" />
            </dialog>

            <div class="workspace-main min-w-0 flex-1">
                <header class="flex flex-wrap items-center justify-between gap-4 px-5 pb-3 pt-6 sm:px-7 lg:px-8 lg:pt-8 print:hidden">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <button type="button" aria-label="Open navigation" aria-haspopup="dialog" class="rounded-xl border border-blue-100 bg-white p-2.5 text-blue-600 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 lg:hidden" @click="mobileNavigation.showModal()">
                            <AppIcon name="menu" class="h-5 w-5" />
                        </button>
                        <div v-if="$slots.header" class="min-w-0 flex-1">
                            <slot name="header" />
                        </div>
                    </div>

                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button type="button" class="flex max-w-[16rem] items-center gap-3 rounded-2xl border border-white bg-white/90 px-3 py-2.5 text-left shadow-sm ring-1 ring-slate-100 transition hover:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Open account menu">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                    <AppIcon name="store" class="h-5 w-5" />
                                </span>
                                <span class="hidden min-w-0 sm:block">
                                    <span class="block text-sm font-semibold text-slate-800">WaterWise POS</span>
                                    <span class="block truncate text-xs text-slate-500">{{ $page.props.auth.user.name }}</span>
                                </span>
                                <AppIcon name="chevron" class="h-4 w-4 shrink-0 text-slate-500" />
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Account settings</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Log out</DropdownLink>
                        </template>
                    </Dropdown>
                </header>

                <main id="main-content" tabindex="-1" class="outline-none">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
.workspace-backdrop {
    min-height: 100vh;
    background-color: #f1f5f9;
}

.workspace-frame {
    display: flex;
    width: 100%;
    min-height: 100dvh;
    background: #f5f9fc;
}

.desktop-sidebar {
    position: sticky;
    top: 0;
    height: 100dvh;
    flex: 0 0 218px;
    overflow: hidden;
}

.mobile-navigation {
    width: min(310px, 88vw);
    height: 100dvh;
    max-height: 100dvh;
    max-width: 100vw;
    margin: 0;
    padding: 0;
    border: 0;
    background: #0063d3;
}

.mobile-navigation::backdrop {
    background: #0c244d66;
    backdrop-filter: blur(3px);
}

@media print {
    .workspace-backdrop,
    .workspace-frame {
        min-height: 0;
        max-width: none;
        margin: 0;
        padding: 0;
        border: 0;
        border-radius: 0;
        background: white;
        background-image: none !important;
        box-shadow: none;
    }
}
</style>
