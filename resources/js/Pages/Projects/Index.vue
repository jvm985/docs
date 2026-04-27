<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    projects: Array,
    sharedProjects: Array,
    publicProjects: Array,
});

const activeTab = ref('my-drive');
const viewMode = ref('grid'); // 'grid' or 'list'
const searchQuery = ref('');

const creatingProject = ref(false);
const sharingProject = ref(null);
const renamingProject = ref(null);
const deletingProject = ref(null);
const activeContextMenu = ref(null); // project.id

const form = useForm({
    name: '',
    description: '',
});

const renameForm = useForm({
    name: '',
});

const shareForm = useForm({
    is_public: false,
    public_role: 'viewer',
    shares: [], // { email: '', role: 'viewer' }
});

const filteredMyProjects = computed(() => {
    if (!searchQuery.value) return props.projects;
    return props.projects.filter(p => p.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const filteredSharedProjects = computed(() => {
    if (!searchQuery.value) return props.sharedProjects;
    return props.sharedProjects.filter(p => p.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const filteredPublicProjects = computed(() => {
    if (!searchQuery.value) return props.publicProjects;
    return props.publicProjects.filter(p => p.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const createProject = () => {
    form.post(route('projects.store'), {
        onSuccess: () => {
            creatingProject.value = false;
            form.reset();
        },
    });
};

const openRenameModal = (project) => {
    activeContextMenu.value = null;
    renamingProject.value = project;
    renameForm.name = project.name;
};

const submitRename = () => {
    renameForm.patch(route('projects.update', renamingProject.value.id), {
        onSuccess: () => renamingProject.value = null,
    });
};

const confirmDelete = (project) => {
    activeContextMenu.value = null;
    deletingProject.value = project;
};

const submitDelete = () => {
    router.delete(route('projects.destroy', deletingProject.value.id), {
        onSuccess: () => deletingProject.value = null,
    });
};

const duplicateProject = (project) => {
    activeContextMenu.value = null;
    router.post(route('projects.duplicate', project.id));
};

const openShareModal = (project) => {
    activeContextMenu.value = null;
    sharingProject.value = project;
    shareForm.is_public = project.is_public;
    shareForm.public_role = project.public_role || 'viewer';
    shareForm.shares = project.shared_users ? project.shared_users.map(u => ({ email: u.email, role: u.pivot.role })) : [];
};

const addShare = () => {
    shareForm.shares.push({ email: '', role: 'viewer' });
};

const removeShare = (index) => {
    shareForm.shares.splice(index, 1);
};

const updateSharing = () => {
    shareForm.patch(route('projects.share.update', sharingProject.value.id), {
        onSuccess: () => sharingProject.value = null,
    });
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('nl-NL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const toggleContextMenu = (id) => {
    if (activeContextMenu.value === id) {
        activeContextMenu.value = null;
    } else {
        activeContextMenu.value = id;
    }
};

// Close context menu on click outside
const closeContextMenu = (e) => {
    if (!e.target.closest('.context-menu-trigger')) {
        activeContextMenu.value = null;
    }
};

onMounted(() => document.addEventListener('click', closeContextMenu));
onUnmounted(() => document.removeEventListener('click', closeContextMenu));

</script>

<template>
    <Head title="Projects - Docs Drive" />

    <AuthenticatedLayout>
        <div class="flex h-[calc(100vh-65px)] bg-[#f8f9fa] overflow-hidden">
            <!-- Sidebar -->
            <aside class="w-64 flex flex-col p-4 space-y-4 shrink-0 border-r border-gray-200 bg-white">
                <button 
                    @click="creatingProject = true"
                    class="flex items-center gap-3 px-5 py-3 rounded-2xl bg-white shadow-md hover:shadow-lg transition-shadow border border-gray-100 text-gray-700 font-medium w-fit"
                >
                    <svg width="24" height="24" viewBox="0 0 24 24"><path fill="#34A853" d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2z"></path><path fill="#4285F4" d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2z"></path><path fill="#FBBC05" d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2z"></path><path fill="#EA4335" d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2z"></path></svg>
                    <span>Nieuw</span>
                </button>

                <nav class="flex flex-col space-y-1 pt-4">
                    <button 
                        @click="activeTab = 'my-drive'"
                        :class="['flex items-center gap-3 px-4 py-2 rounded-full text-sm font-medium transition-colors', activeTab === 'my-drive' ? 'bg-[#e8f0fe] text-[#1967d2]' : 'text-gray-700 hover:bg-gray-100']"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                        Mijn Drive
                    </button>
                    <button 
                        @click="activeTab = 'shared'"
                        :class="['flex items-center gap-3 px-4 py-2 rounded-full text-sm font-medium transition-colors', activeTab === 'shared' ? 'bg-[#e8f0fe] text-[#1967d2]' : 'text-gray-700 hover:bg-gray-100']"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Gedeeld met mij
                    </button>
                    <button 
                        @click="activeTab = 'community'"
                        :class="['flex items-center gap-3 px-4 py-2 rounded-full text-sm font-medium transition-colors', activeTab === 'community' ? 'bg-[#e8f0fe] text-[#1967d2]' : 'text-gray-700 hover:bg-gray-100']"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                        Community
                    </button>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="flex-grow flex flex-col overflow-hidden">
                <!-- Search Bar Area -->
                <div class="h-16 flex items-center px-6 gap-4 bg-white border-b border-gray-200">
                    <div class="flex-grow max-w-2xl bg-gray-100 rounded-lg flex items-center px-4 py-2 focus-within:bg-white focus-within:shadow-md border border-transparent focus-within:border-blue-200 transition-all">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Zoeken in Drive" 
                            class="bg-transparent border-none focus:ring-0 w-full text-sm placeholder-gray-500"
                        >
                    </div>
                    <div class="flex-grow"></div>
                    <div class="flex items-center gap-2">
                        <button 
                            @click="viewMode = viewMode === 'grid' ? 'list' : 'grid'"
                            class="p-2 rounded-full hover:bg-gray-100 text-gray-600"
                        >
                            <svg v-if="viewMode === 'grid'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 h-2a2 2 0 01-2-2v-2z"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Content Area -->
                <div class="flex-grow overflow-auto p-6 scrollbar-thin">
                    <div v-if="activeTab === 'my-drive'">
                        <h2 class="text-sm font-medium text-gray-700 mb-6 uppercase tracking-wider text-left">Mijn Bestanden</h2>
                        
                        <!-- Grid View -->
                        <div v-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                            <div v-for="project in filteredMyProjects" :key="project.id" class="group bg-white rounded-xl border border-gray-200 hover:shadow-md hover:bg-blue-50 transition-all p-3 flex flex-col relative">
                                <Link :href="route('projects.show', project.id)" class="absolute inset-0 z-0"></Link>
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="p-2 rounded bg-blue-100 text-blue-600">
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-800 truncate">{{ project.name }}</span>
                                    <div class="flex-grow"></div>
                                    
                                    <!-- Context Menu -->
                                    <div class="relative z-20">
                                        <button @click.stop="toggleContextMenu(project.id)" class="context-menu-trigger p-1.5 rounded-full hover:bg-gray-200 group-hover:opacity-100 transition-opacity">
                                            <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path></svg>
                                        </button>
                                        
                                        <div v-if="activeContextMenu === project.id" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 py-1 z-30">
                                            <button @click.stop="openRenameModal(project)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                Hernoemen
                                            </button>
                                            <button @click.stop="duplicateProject(project)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                                                Kopiëren
                                            </button>
                                            <button @click.stop="openShareModal(project)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m.496-8.213a4.817 4.817 0 01-8.163 0m11.364 0a4.817 4.817 0 01-8.163 0m.002 11.364a4.817 4.817 0 01-8.163 0m11.364 0a4.817 4.817 0 01-8.163 0"></path></svg>
                                                Delen
                                            </button>
                                            <div class="border-t border-gray-100 my-1"></div>
                                            <button @click.stop="confirmDelete(project)" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                Verwijderen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="h-24 bg-gray-50 rounded border border-gray-100 flex items-center justify-center text-gray-300 text-xs italic">
                                    Geen preview
                                </div>
                            </div>
                        </div>

                        <!-- List View -->
                        <div v-else class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                            <table class="w-full text-left text-sm text-gray-600">
                                <thead class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase text-gray-500">
                                    <tr>
                                        <th class="px-6 py-3">Naam</th>
                                        <th class="px-6 py-3">Eigenaar</th>
                                        <th class="px-6 py-3">Laatst gewijzigd</th>
                                        <th class="px-6 py-3 text-right">Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="project in filteredMyProjects" :key="project.id" class="group hover:bg-blue-50 border-b border-gray-100 transition-colors cursor-pointer relative">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                                <Link :href="route('projects.show', project.id)" class="font-medium text-gray-800 hover:underline">{{ project.name }}</Link>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">mij</td>
                                        <td class="px-6 py-4">{{ formatDate(project.updated_at) }}</td>
                                        <td class="px-6 py-4 text-right relative">
                                            <button @click.stop="toggleContextMenu(project.id)" class="context-menu-trigger p-1.5 rounded-full hover:bg-gray-200 text-gray-400">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path></svg>
                                            </button>
                                            
                                            <div v-if="activeContextMenu === project.id" class="absolute right-6 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 py-1 z-30">
                                                <button @click.stop="openRenameModal(project)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                                    Hernoemen
                                                </button>
                                                <button @click.stop="duplicateProject(project)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                                                    Kopiëren
                                                </button>
                                                <button @click.stop="openShareModal(project)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m.496-8.213a4.817 4.817 0 01-8.163 0m11.364 0a4.817 4.817 0 01-8.163 0m.002 11.364a4.817 4.817 0 01-8.163 0m11.364 0a4.817 4.817 0 01-8.163 0"></path></svg>
                                                    Delen
                                                </button>
                                                <div class="border-t border-gray-100 my-1"></div>
                                                <button @click.stop="confirmDelete(project)" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    Verwijderen
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Shared Projects View -->
                    <div v-if="activeTab === 'shared'">
                        <h2 class="text-sm font-medium text-gray-700 mb-6 uppercase tracking-wider text-left">Gedeeld met mij</h2>
                        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden text-left">
                            <table class="w-full text-left text-sm text-gray-600">
                                <thead class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase text-gray-500">
                                    <tr>
                                        <th class="px-6 py-3">Naam</th>
                                        <th class="px-6 py-3">Gedeeld door</th>
                                        <th class="px-6 py-3">Rol</th>
                                        <th class="px-6 py-3">Datum gedeeld</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="project in filteredSharedProjects" :key="project.id" class="group hover:bg-blue-50 border-b border-gray-100 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <svg class="w-5 h-5 text-purple-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                                <Link :href="route('projects.show', project.id)" class="font-medium text-gray-800 hover:underline">{{ project.name }}</Link>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">{{ project.user?.name }}</td>
                                        <td class="px-6 py-4 capitalize">{{ project.pivot?.role }}</td>
                                        <td class="px-6 py-4">{{ formatDate(project.pivot?.created_at || project.updated_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Community Projects View -->
                    <div v-if="activeTab === 'community'">
                        <h2 class="text-sm font-medium text-gray-700 mb-6 uppercase tracking-wider text-left">Community Projecten</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 text-left">
                            <div v-for="project in filteredPublicProjects" :key="project.id" class="group bg-white rounded-xl border border-gray-200 hover:shadow-md hover:bg-green-50 transition-all p-4 flex flex-col relative">
                                <Link :href="route('projects.show', project.id)" class="absolute inset-0 z-0"></Link>
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="p-2 rounded bg-green-100 text-green-600">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg>
                                    </div>
                                    <span class="text-sm font-medium text-gray-800 truncate">{{ project.name }}</span>
                                </div>
                                <p class="text-xs text-gray-400">Door: {{ project.user?.name }}</p>
                                <p class="text-xs text-gray-500 mt-2 line-clamp-2">{{ project.description || 'Geen beschrijving' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <!-- Create Modal -->
        <Modal :show="creatingProject" @close="creatingProject = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Nieuw Project maken</h2>
                <form @submit.prevent="createProject" class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="name" value="Projectnaam" />
                        <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
                    </div>
                    <div>
                        <InputLabel for="description" value="Beschrijving" />
                        <TextInput id="description" type="text" class="mt-1 block w-full" v-model="form.description" />
                    </div>
                    <div class="flex justify-end gap-4">
                        <SecondaryButton @click="creatingProject = false">Annuleren</SecondaryButton>
                        <PrimaryButton :disabled="form.processing">Maken</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Rename Modal -->
        <Modal :show="!!renamingProject" @close="renamingProject = null">
            <div class="p-6 text-left">
                <h2 class="text-lg font-medium text-gray-900">Hernoemen</h2>
                <p class="text-sm text-gray-500 mt-1">Geef een nieuwe naam op voor het item:</p>
                <form @submit.prevent="submitRename" class="mt-6">
                    <TextInput type="text" class="block w-full rounded-lg" v-model="renameForm.name" required autofocus />
                    <div class="flex justify-end gap-4 mt-6">
                        <SecondaryButton @click="renamingProject = null" class="rounded-full px-6">Annuleren</SecondaryButton>
                        <PrimaryButton :disabled="renameForm.processing" class="rounded-full px-8">OK</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="!!deletingProject" @close="deletingProject = null">
            <div class="p-6 text-left">
                <h2 class="text-lg font-medium text-red-600 flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Item verwijderen?
                </h2>
                <p class="text-sm text-gray-500 mt-4">
                    Weet je zeker dat je <strong>{{ deletingProject?.name }}</strong> wilt verwijderen? Dit kan niet ongedaan worden gemaakt.
                </p>
                <div class="flex justify-end gap-4 mt-8">
                    <SecondaryButton @click="deletingProject = null" class="rounded-full px-6">Annuleren</SecondaryButton>
                    <button @click="submitDelete" class="bg-red-600 text-white rounded-full px-8 py-2 text-sm font-bold hover:bg-red-700 transition-colors">
                        Verwijderen
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Share Modal -->
        <Modal :show="!!sharingProject" @close="sharingProject = null">
            <div class="p-6 text-left">
                <h2 class="text-lg font-medium text-gray-900 flex items-center gap-2">
                    <svg class="w-6 h-6 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z"></path></svg>
                    Delen: {{ sharingProject?.name }}
                </h2>
                
                <div class="mt-6 space-y-6">
                    <!-- Global Sharing -->
                    <div class="p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" v-model="shareForm.is_public" class="rounded text-blue-600 focus:ring-blue-500 border-gray-300">
                            <div class="flex flex-col">
                                <span class="font-bold text-sm group-hover:text-blue-600 transition-colors">Openbaar maken</span>
                                <span class="text-[10px] text-gray-400 uppercase tracking-wider">Iedereen met de link kan het project zien</span>
                            </div>
                        </label>
                        <div v-if="shareForm.is_public" class="mt-4 flex items-center gap-4 border-t border-gray-200 pt-4">
                            <span class="text-xs text-gray-500 font-bold uppercase tracking-widest">Rol:</span>
                            <select v-model="shareForm.public_role" class="text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <option value="viewer">Viewer (Alleen lezen)</option>
                                <option value="editor">Editor (Lezen & Schrijven)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Individual Sharing -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400">Delen met specifieke personen</h3>
                            <button @click="addShare" class="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                Persoon toevoegen
                            </button>
                        </div>
                        
                        <div class="space-y-3 max-h-48 overflow-auto pr-2 scrollbar-thin">
                            <div v-for="(share, index) in shareForm.shares" :key="index" class="flex gap-2 items-center animate-in fade-in slide-in-from-top-1 duration-200">
                                <TextInput type="email" v-model="share.email" placeholder="Email adres" class="flex-grow text-sm rounded-lg" />
                                <select v-model="share.role" class="text-sm border-gray-300 rounded-lg">
                                    <option value="viewer">Viewer</option>
                                    <option value="editor">Editor</option>
                                </select>
                                <button @click="removeShare(index)" class="text-gray-400 hover:text-red-500 p-1.5 rounded-full hover:bg-red-50 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <div v-if="shareForm.shares.length === 0" class="text-center py-4 bg-gray-50 rounded-lg border border-dashed text-gray-400 text-xs text-center">
                                Geen personen toegevoegd
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-100">
                        <SecondaryButton @click="sharingProject = null" class="rounded-full px-6">Annuleren</SecondaryButton>
                        <PrimaryButton @click="updateSharing" :disabled="shareForm.processing" class="rounded-full px-8">Klaar</PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style>
.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
