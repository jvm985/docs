<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import { ref, computed } from 'vue';

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

const form = useForm({
    name: '',
    description: '',
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

const openShareModal = (project) => {
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
                            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Content Area -->
                <div class="flex-grow overflow-auto p-6 scrollbar-thin">
                    <div v-if="activeTab === 'my-drive'">
                        <h2 class="text-sm font-medium text-gray-700 mb-6 uppercase tracking-wider">Mijn Bestanden</h2>
                        
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
                                    <button @click.stop="openShareModal(project)" class="p-1.5 rounded-full hover:bg-gray-200 opacity-0 group-hover:opacity-100 transition-opacity z-10">
                                        <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z"></path></svg>
                                    </button>
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
                                        <th class="px-6 py-3">Grootte</th>
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
                                        <td class="px-6 py-4">-</td>
                                        <td class="px-6 py-4 text-right">
                                            <button @click="openShareModal(project)" class="p-1.5 rounded-full hover:bg-gray-200 text-gray-400 hover:text-gray-600">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z"></path></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Shared Projects View -->
                    <div v-if="activeTab === 'shared'">
                        <h2 class="text-sm font-medium text-gray-700 mb-6 uppercase tracking-wider">Gedeeld met mij</h2>
                        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
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
                        <h2 class="text-sm font-medium text-gray-700 mb-6 uppercase tracking-wider">Community Projecten</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
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

        <!-- Share Modal -->
        <Modal :show="!!sharingProject" @close="sharingProject = null">
            <div class="p-6">
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
                            <div v-if="shareForm.shares.length === 0" class="text-center py-4 bg-gray-50 rounded-lg border border-dashed text-gray-400 text-xs">
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
