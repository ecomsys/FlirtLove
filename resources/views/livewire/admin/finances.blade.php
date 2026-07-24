<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.admin')] class extends Component 
{
    // Заглушка
}; ?>

<!-- Табы на Alpine.js -->
<div x-data="{ tab: 'transactions' }" class="space-y-6">
    <h1 class="text-2xl font-semibold">Финансы и монетизация</h1>

    <!-- Меню вкладок -->
    <div class="border-b border-border">
        <nav class="flex gap-4">
            <button @click="tab = 'transactions'" :class="tab === 'transactions' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">Транзакции</button>
            <button @click="tab = 'subscriptions'" :class="tab === 'subscriptions' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">Премиум-подписки</button>
            <button @click="tab = 'promocodes'" :class="tab === 'promocodes' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">Промокоды</button>
        </nav>
    </div>

    <!-- Контент -->
    <div class="bg-card border border-border rounded-lg p-12 text-center text-muted-foreground">
        <div x-show="tab === 'transactions'">История всех платежей (Будет реализовано позже)</div>
        <div x-show="tab === 'subscriptions'" style="display: none;">Управление премиумом юзеров</div>
        <div x-show="tab === 'promocodes'" style="display: none;">Генерация промокодов</div>
    </div>
</div>