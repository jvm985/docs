<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import { ref } from 'vue';

const props = defineProps({
    projects: Array,
});

const creatingProject = ref(false);
const form = useForm({
    name: '',
    description: '',
});

const createProject = () => {
    form.post(route('projects.store'), {
        onSuccess: () => {
            creatingProject.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Projects" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Projects</h2>
                <PrimaryButton dusk="new-project-button" @click="creatingProject = true">New Project</PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="project in projects" :key="project.id" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900">
                            <h3 class="text-lg font-bold">
                                <Link :href="route('projects.show', project.id)" class="hover:underline">
                                    {{ project.name }}
                                </Link>
                            </h3>
                            <p class="text-gray-600 mt-2">{{ project.description }}</p>
                            <div class="mt-4 text-sm text-gray-400">
                                Created at {{ new Date(project.created_at).toLocaleDateString() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                        <PrimaryButton dusk="create-project-button" :disabled="form.processing">Create</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
