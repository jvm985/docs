<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import { ref } from 'vue';

const props = defineProps({
    projects: Array,
    sharedProjects: Array,
    publicProjects: Array,
});

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
</script>

<template>
    <Head title="Projects" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Workspace</h2>
                    <span class="text-[10px] text-green-500 font-bold font-mono px-2 py-0.5 bg-green-50 rounded border border-green-200">🔥 FINAL-FIX-V5</span>
                </div>
                <PrimaryButton dusk="new-project-button" @click="creatingProject = true">New Project</PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                <!-- My Projects -->
                <section>
                    <h3 class="text-lg font-medium text-gray-600 mb-4">My Projects</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="project in projects" :key="project.id" class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-t-4 border-blue-500">
                            <div class="p-6">
                                <div class="flex justify-between items-start">
                                    <h4 class="text-lg font-bold">
                                        <Link :href="route('projects.show', project.id)" class="hover:underline text-blue-600">
                                            {{ project.name }}
                                        </Link>
                                    </h4>
                                    <button @click="openShareModal(project)" class="text-gray-400 hover:text-gray-600" title="Share Project">🔗</button>
                                </div>
                                <p class="text-gray-600 mt-2 text-sm line-clamp-2">{{ project.description || 'No description' }}</p>
                                <div class="mt-4 flex items-center gap-2">
                                    <span v-if="project.is_public" class="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] rounded-full uppercase font-bold">Public ({{ project.public_role }})</span>
                                    <span class="text-[10px] text-gray-400 uppercase">Created {{ new Date(project.created_at).toLocaleDateString() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Shared With Me -->
                <section v-if="sharedProjects.length > 0">
                    <h3 class="text-lg font-medium text-gray-600 mb-4">Shared With Me</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="project in sharedProjects" :key="project.id" class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-t-4 border-purple-500">
                            <div class="p-6">
                                <h4 class="text-lg font-bold">
                                    <Link :href="route('projects.show', project.id)" class="hover:underline text-purple-600">
                                        {{ project.name }}
                                    </Link>
                                </h4>
                                <p class="text-xs text-gray-400 mt-1">Owner: {{ project.user?.name }}</p>
                                <p class="text-gray-600 mt-2 text-sm line-clamp-2">{{ project.description }}</p>
                                <div class="mt-4">
                                    <span class="px-2 py-0.5 bg-purple-100 text-purple-700 text-[10px] rounded-full uppercase font-bold">{{ project.pivot?.role }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Public Projects -->
                <section v-if="publicProjects.length > 0">
                    <h3 class="text-lg font-medium text-gray-600 mb-4">Community Projects</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="project in publicProjects" :key="project.id" class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-t-4 border-green-500">
                            <div class="p-6">
                                <h4 class="text-lg font-bold">
                                    <Link :href="route('projects.show', project.id)" class="hover:underline text-green-600">
                                        {{ project.name }}
                                    </Link>
                                </h4>
                                <p class="text-xs text-gray-400 mt-1">By: {{ project.user?.name }}</p>
                                <p class="text-gray-600 mt-2 text-sm line-clamp-2">{{ project.description }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- Create Modal -->
        <Modal :show="creatingProject" @close="creatingProject = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Create New Project</h2>
                <form @submit.prevent="createProject" class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="name" value="Project Name" />
                        <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
                    </div>
                    <div>
                        <InputLabel for="description" value="Description" />
                        <TextInput id="description" type="text" class="mt-1 block w-full" v-model="form.description" />
                    </div>
                    <div class="flex justify-end gap-4">
                        <SecondaryButton @click="creatingProject = false">Cancel</SecondaryButton>
                        <PrimaryButton :disabled="form.processing">Create</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Share Modal -->
        <Modal :show="!!sharingProject" @close="sharingProject = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Share Project: {{ sharingProject?.name }}</h2>
                
                <div class="mt-6 space-y-6">
                    <!-- Global Sharing -->
                    <div class="p-4 bg-gray-50 rounded-lg border">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" v-model="shareForm.is_public" class="rounded text-blue-600">
                            <span class="font-bold text-sm">Visible to everyone</span>
                        </label>
                        <div v-if="shareForm.is_public" class="mt-3 flex items-center gap-4">
                            <span class="text-xs text-gray-500 uppercase">Public Role:</span>
                            <select v-model="shareForm.public_role" class="text-sm border-gray-300 rounded">
                                <option value="viewer">Viewer (Read Only)</option>
                                <option value="editor">Editor (Read & Write)</option>
                            </select>
                        </div>
                    </div>

                    <hr>

                    <!-- Individual Sharing -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-sm font-bold uppercase text-gray-400">Share with individuals</h3>
                            <button @click="addShare" class="text-xs text-blue-600 hover:underline">+ Add User</button>
                        </div>
                        
                        <div class="space-y-3">
                            <div v-for="(share, index) in shareForm.shares" :key="index" class="flex gap-2 items-center">
                                <TextInput type="email" v-model="share.email" placeholder="User email" class="flex-grow text-sm" />
                                <select v-model="share.role" class="text-sm border-gray-300 rounded">
                                    <option value="viewer">Viewer</option>
                                    <option value="editor">Editor</option>
                                </select>
                                <button @click="removeShare(index)" class="text-red-500 p-1">✕</button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 pt-4 border-t">
                        <SecondaryButton @click="sharingProject = null">Cancel</SecondaryButton>
                        <PrimaryButton @click="updateSharing" :disabled="shareForm.processing">Save Changes</PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
