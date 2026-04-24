<script setup>
import OutputPanel from '@/Components/Project/OutputPanel.vue';
defineProps(['testData']);
</script>
<template>
    <div class="h-screen w-screen flex items-center justify-center p-10 bg-gray-500">
        <div class="w-1/2 h-full bg-white shadow-2xl border-4 border-red-500">
            <OutputPanel 
                :type="testData.type" 
                :rOutput="testData.result" 
                content="Raw text fallback"
            />
        </div>
    </div>
</template>
