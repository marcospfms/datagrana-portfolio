# Roadmap V7 - Sistema de Assinatura Simplificado com Limites

**Status:** 🔄 Planejamento
**Dependências:** V1-V6 (completos)
**Objetivo:** Simplificar sistema de assinatura com limites por funcionalidade e integração com RevenueCat

---

## 📋 Visão Geral

A estrutura atual de assinatura é complexa com múltiplas tabelas (`plan`, `plan_period`, `plan_feature`, `subscription`, `billing_type`, etc). Esta versão simplifica drasticamente o modelo, focando em **limites por funcionalidade** e integração com **RevenueCat** para facilitar compras nas lojas móveis.

### Modelo Simplificado

```
Plano de Assinatura (Subscription Plan)
├── Define limites máximos por funcionalidade
├── Preço mensal fixo
└── Status ativo/inativo

Assinatura do Usuário (User Subscription)
├── Referencia um plano
├── Copia limites do plano (snapshot)
├── Controla status e renovação
└── Integra com RevenueCat (lojas móveis)
```

---

## 🎯 Planos Propostos

### 1. Plano Gratuito (Free)
**Público:** Usuários iniciantes explorando o app
**Preço:** R$ 0,00/mês

**Limites:**
- ✅ 1 portfólio
- ✅ 10 composições totais (ativos na carteira)
- ✅ 10 posições ativas (consolidados)
- ✅ 1 conta (broker)
- ✅ Operações ilimitadas
- ❌ **Crossing limitado:** Esconde profit/loss, totais consolidados
- ❌ Sem histórico de composições deletadas
- ❌ Sem análise avançada por categoria

> **Regra de downgrade (quando excede limites):**
> - Itens acima do limite **não são deletados**, mas ficam **bloqueados para edição/exclusão**.
> - Sempre permanecem editáveis os **mais antigos** (ordenados por `created_at`).
> - Aplica-se a: contas, carteiras, composições (por carteira) e posições ativas.

### 2. Plano Investidor Iniciante (Starter)
**Público:** Investidores começando a diversificar
**Preço:** R$ 19,90/mês

**Limites:**
- ✅ 2 portfólios
- ✅ 25 composições totais
- ✅ 25 posições ativas
- ✅ 2 contas (brokers)
- ✅ Operações ilimitadas
- ✅ **Crossing completo:** Todos os dados visíveis
- ✅ Histórico de composições
- ✅ Análise por categoria

### 3. Plano Investidor Pro (Pro)
**Público:** Investidores ativos com múltiplas estratégias
**Preço:** R$ 39,90/mês

**Limites:**
- ✅ 4 portfólios (dobro do Iniciante)
- ✅ 50 composições totais (dobro do Iniciante)
- ✅ 50 posições ativas (dobro do Iniciante)
- ✅ 4 contas (dobro do Iniciante)
- ✅ Operações ilimitadas
- ✅ **Crossing completo:** Todos os dados visíveis
- ✅ Histórico completo
- ✅ Análise avançada por categoria
- ✅ Análise comparativa multi-portfólio

### 4. Plano Investidor Premium (Premium)
**Público:** Investidores profissionais
**Preço:** R$ 79,90/mês

**Limites:**
- ✅ **Portfólios ilimitados**
- ✅ **Composições ilimitadas**
- ✅ **Posições ilimitadas**
- ✅ **Contas ilimitadas**
- ✅ Operações ilimitadas
- ✅ **Crossing completo + insights avançados**
- ✅ Histórico completo
- ✅ Análise avançada por categoria
- ✅ Análise comparativa multi-portfólio
- ✅ Prioridade no suporte

---

## 🔒 Regra de Bloqueio por Limite (Downgrade)

Quando o usuário reduz o plano e passa a exceder limites:

- **Não removemos dados** automaticamente.
- **Bloqueamos edição/remoção** de itens fora do limite.
- **Critério:** somente os **N mais antigos** (`created_at` asc) permanecem editáveis.
- **Escopos:**
  - **Contas:** limite global por usuário.
  - **Carteiras:** limite global por usuário.
  - **Composições:** limite **por carteira**.
  - **Posições ativas:** limite global por usuário.

### Validação no Backend (obrigatória)

Todas as operações de banco devem validar:

- Update/Destroy em contas, carteiras e composições.
- Transações que alterem posições ativas (criar/editar/excluir transação).
- Se a posição já existe, validar se ela está entre as **mais antigas**.
- Se a posição é nova, validar criação com limite.

### Fonte de verdade dos limites (backend)

- **Sempre calcular limites e bloqueios no backend** (ex.: `is_locked`).
- O frontend **não deve** recomputar regras de limite/ordenação localmente.
- Recursos/listas devem **expor campos calculados** para consumo direto no app:
  - `is_locked` em contas, carteiras, composições e posições.
- Objetivo: evitar inconsistência, delays e bypass por engenharia reversa.

---

## 🗄️ Estrutura de Banco de Dados

### Nova Estrutura Simplificada (EAV Pattern)

A estrutura usa o padrão **Entity-Attribute-Value** onde cada configuração é uma **linha** ao invés de coluna, permitindo crescimento infinito sem alterar schema.

```sql
-- Tabela de Planos (Apenas informações básicas)
CREATE TABLE subscription_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Identificação
  name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Gratuito, Investidor Iniciante, Investidor Pro, Premium',
  slug VARCHAR(50) NOT NULL UNIQUE COMMENT 'free, starter, pro, premium',
  description TEXT NULL COMMENT 'Descrição do plano',

  -- Preço
  price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço mensal em BRL',

  -- Controle
  is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Plano disponível para contratação',
  display_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição no app',

  -- RevenueCat
  revenuecat_product_id VARCHAR(100) NULL COMMENT 'Product ID no RevenueCat',
  revenuecat_entitlement_id VARCHAR(100) NULL COMMENT 'Entitlement ID no RevenueCat',

  -- Auditoria
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,

  INDEX idx_active_plans (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Configurações Unificada (Limites + Features)
CREATE TABLE subscription_plan_config (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  subscription_plan_id BIGINT UNSIGNED NOT NULL,

  -- Chave da configuração (ex: 'max_portfolios', 'allow_full_crossing')
  config_key VARCHAR(50) NOT NULL COMMENT 'Identificador da configuração',

  -- Valor do limite (usado para max_*, NULL = ilimitado)
  config_value INT NULL COMMENT 'Valor numérico (NULL = ilimitado para max_*)',

  -- Flag de habilitação (usado para allow_*)
  is_enabled BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Feature habilitada (para allow_*)',

  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,

  FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE,

  UNIQUE INDEX idx_plan_config (subscription_plan_id, config_key),
  INDEX idx_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*
Convenção de Nomenclatura:

1. Limites numéricos (prefixo "max_"):
   - config_key: 'max_portfolios', 'max_compositions', etc
   - config_value: número ou NULL (ilimitado)
   - is_enabled: não usado (sempre FALSE)

2. Features booleanas (prefixo "allow_"):
   - config_key: 'allow_full_crossing', 'allow_category_analysis', etc
   - config_value: não usado (sempre NULL)
   - is_enabled: TRUE ou FALSE

Exemplos:
  ('max_portfolios', 2, FALSE)           → Limite de 2 portfólios
  ('max_portfolios', NULL, FALSE)        → Portfólios ilimitados
  ('allow_full_crossing', NULL, TRUE)    → Feature habilitada
  ('allow_full_crossing', NULL, FALSE)   → Feature desabilitada
*/

-- Tabela de Assinaturas dos Usuários
CREATE TABLE user_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Relacionamentos
  user_id BIGINT UNSIGNED NOT NULL,
  subscription_plan_id BIGINT UNSIGNED NOT NULL,

  -- Snapshot do plano (captura no momento da contratação)
  plan_name VARCHAR(100) NOT NULL COMMENT 'Nome do plano contratado',
  plan_slug VARCHAR(50) NOT NULL COMMENT 'Slug do plano',
  price_monthly DECIMAL(10,2) NOT NULL COMMENT 'Preço contratado',

  -- Snapshot de limites e features (JSON para flexibilidade)
  limits_snapshot JSON NULL COMMENT 'Snapshot dos limites no momento da contratação',
  features_snapshot JSON NULL COMMENT 'Snapshot das features no momento da contratação',

  -- Status da assinatura
  status ENUM('active', 'expired', 'canceled', 'trialing', 'pending') DEFAULT 'active',

  -- Datas
  starts_at TIMESTAMP NOT NULL COMMENT 'Data de início',
  ends_at TIMESTAMP NULL COMMENT 'Data de expiração (NULL = vitalício)',
  renews_at TIMESTAMP NULL COMMENT 'Data da próxima renovação',
  trial_ends_at TIMESTAMP NULL COMMENT 'Fim do período de trial',
  canceled_at TIMESTAMP NULL COMMENT 'Data do cancelamento',

  -- Pagamento
  is_paid BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Pagamento confirmado',
  paid_at TIMESTAMP NULL,
  payment_method VARCHAR(50) NULL COMMENT 'pix, boleto, credit_card, app_store, play_store',

  -- RevenueCat Integration
  revenuecat_subscriber_id VARCHAR(191) NULL COMMENT 'Subscriber ID no RevenueCat',
  revenuecat_original_transaction_id VARCHAR(191) NULL COMMENT 'Transaction ID original',
  revenuecat_product_id VARCHAR(100) NULL COMMENT 'Product ID comprado',
  revenuecat_entitlement_id VARCHAR(100) NULL COMMENT 'Entitlement ativo',
  revenuecat_store VARCHAR(20) NULL COMMENT 'app_store, play_store, stripe, promotional',
  revenuecat_raw_data JSON NULL COMMENT 'Dados completos do webhook',

  -- Metadados
  cancellation_reason TEXT NULL,
  notes TEXT NULL COMMENT 'Observações administrativas',

  -- Auditoria
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,

  -- FKs
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT,

  -- Indexes
  INDEX idx_user_active_subscription (user_id, status, ends_at),
  INDEX idx_renewal (renews_at, status),
  INDEX idx_revenuecat_subscriber (revenuecat_subscriber_id),
  UNIQUE INDEX idx_revenuecat_transaction (revenuecat_original_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*
Estrutura dos JSONs de snapshot:

limits_snapshot:
{
  "max_portfolios": 2,
  "max_compositions": 25,
  "max_positions": 25,
  "max_accounts": 2
}

features_snapshot:
{
  "allow_full_crossing": true,
  "allow_composition_history": true,
  "allow_category_analysis": true,
  "allow_multi_portfolio_analysis": false
}
*/

-- Tabela de Log de Webhooks do RevenueCat
CREATE TABLE revenuecat_webhook_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Identificação
  event_type VARCHAR(100) NOT NULL COMMENT 'INITIAL_PURCHASE, RENEWAL, CANCELLATION, etc',
  app_user_id VARCHAR(191) NULL COMMENT 'App User ID (nosso user_id)',
  subscriber_id VARCHAR(191) NULL COMMENT 'Subscriber ID no RevenueCat',

  -- Dados do evento
  product_id VARCHAR(100) NULL,
  entitlement_id VARCHAR(100) NULL,
  store VARCHAR(20) NULL COMMENT 'app_store, play_store, stripe, promotional',
  original_transaction_id VARCHAR(191) NULL,

  -- Payload completo
  payload JSON NOT NULL COMMENT 'Webhook payload completo',

  -- Processamento
  status ENUM('pending', 'processed', 'failed') DEFAULT 'pending',
  processed_at TIMESTAMP NULL,
  error_message TEXT NULL,

  -- Auditoria
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,

  INDEX idx_status (status, created_at),
  INDEX idx_subscriber (subscriber_id),
  INDEX idx_app_user (app_user_id),
  INDEX idx_transaction (original_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Uso Atual (Cache de contadores)
CREATE TABLE user_subscription_usage (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  user_id BIGINT UNSIGNED NOT NULL,
  user_subscription_id BIGINT UNSIGNED NOT NULL,

  -- Contadores atuais
  current_portfolios INT NOT NULL DEFAULT 0,
  current_compositions INT NOT NULL DEFAULT 0 COMMENT 'Total de composições em todos os portfólios',
  current_positions INT NOT NULL DEFAULT 0 COMMENT 'Total de posições ativas (closed = false)',
  current_accounts INT NOT NULL DEFAULT 0,

  -- Última atualização
  last_calculated_at TIMESTAMP NULL,

  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,

  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (user_subscription_id) REFERENCES user_subscriptions(id) ON DELETE CASCADE,

  UNIQUE INDEX idx_user_subscription (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Seed Inicial dos Planos

```sql
-- 1. Inserir Planos Básicos
INSERT INTO subscription_plans (name, slug, description, price_monthly, is_active, display_order, revenuecat_product_id, revenuecat_entitlement_id) VALUES
('Gratuito', 'free', 'Ideal para começar a organizar seus investimentos', 0.00, TRUE, 1, NULL, NULL),
('Investidor Iniciante', 'starter', 'Para investidores começando a diversificar', 19.90, TRUE, 2, 'datagrana_starter_monthly', 'starter'),
('Investidor Pro', 'pro', 'Para investidores ativos com múltiplas estratégias', 39.90, TRUE, 3, 'datagrana_pro_monthly', 'pro'),
('Premium', 'premium', 'Recursos ilimitados para investidores profissionais', 79.90, TRUE, 4, 'datagrana_premium_monthly', 'premium');

-- 2. Inserir Configurações (Limites + Features) em uma única tabela
-- Plano Gratuito (ID 1)
INSERT INTO subscription_plan_config (subscription_plan_id, config_key, config_value, is_enabled) VALUES
-- Limites
(1, 'max_portfolios', 1, FALSE),
(1, 'max_compositions', 10, FALSE),
(1, 'max_positions', 10, FALSE),
(1, 'max_accounts', 1, FALSE),
-- Features
(1, 'allow_full_crossing', NULL, FALSE),
(1, 'allow_composition_history', NULL, FALSE),
(1, 'allow_category_analysis', NULL, FALSE),
(1, 'allow_multi_portfolio_analysis', NULL, FALSE);

-- Plano Investidor Iniciante (ID 2)
INSERT INTO subscription_plan_config (subscription_plan_id, config_key, config_value, is_enabled) VALUES
-- Limites
(2, 'max_portfolios', 2, FALSE),
(2, 'max_compositions', 25, FALSE),
(2, 'max_positions', 25, FALSE),
(2, 'max_accounts', 2, FALSE),
-- Features
(2, 'allow_full_crossing', NULL, TRUE),
(2, 'allow_composition_history', NULL, TRUE),
(2, 'allow_category_analysis', NULL, TRUE),
(2, 'allow_multi_portfolio_analysis', NULL, FALSE);

-- Plano Investidor Pro (ID 3)
INSERT INTO subscription_plan_config (subscription_plan_id, config_key, config_value, is_enabled) VALUES
-- Limites
(3, 'max_portfolios', 4, FALSE),
(3, 'max_compositions', 50, FALSE),
(3, 'max_positions', 50, FALSE),
(3, 'max_accounts', 4, FALSE),
-- Features
(3, 'allow_full_crossing', NULL, TRUE),
(3, 'allow_composition_history', NULL, TRUE),
(3, 'allow_category_analysis', NULL, TRUE),
(3, 'allow_multi_portfolio_analysis', NULL, TRUE);

-- Plano Premium (ID 4) - Limites ilimitados (NULL)
INSERT INTO subscription_plan_config (subscription_plan_id, config_key, config_value, is_enabled) VALUES
-- Limites (NULL = ilimitado)
(4, 'max_portfolios', NULL, FALSE),
(4, 'max_compositions', NULL, FALSE),
(4, 'max_positions', NULL, FALSE),
(4, 'max_accounts', NULL, FALSE),
-- Features (todas habilitadas)
(4, 'allow_full_crossing', NULL, TRUE),
(4, 'allow_composition_history', NULL, TRUE),
(4, 'allow_category_analysis', NULL, TRUE),
(4, 'allow_multi_portfolio_analysis', NULL, TRUE);
```

**Chaves de Configuração Disponíveis:**

**Limites (prefixo `max_`):**
- `max_portfolios` - Máximo de portfólios
- `max_compositions` - Máximo de composições totais (somando todos os portfólios)
- `max_positions` - Máximo de posições ativas (consolidados não fechados)
- `max_accounts` - Máximo de contas/brokers

**Features (prefixo `allow_`):**
- `allow_full_crossing` - Acesso completo à tela de crossing (mostra profit/loss)
- `allow_composition_history` - Histórico de composições deletadas
- `allow_category_analysis` - Análise avançada por categoria
- `allow_multi_portfolio_analysis` - Análise comparativa de múltiplos portfólios

**Para adicionar novos limites/features no futuro:**
```sql
-- Exemplo: adicionar novo limite
INSERT INTO subscription_plan_config (subscription_plan_id, config_key, config_value, is_enabled)
SELECT id, 'max_price_alerts',
  CASE
    WHEN slug = 'free' THEN 5
    WHEN slug = 'starter' THEN 20
    WHEN slug = 'pro' THEN 50
    WHEN slug = 'premium' THEN NULL
  END,
  FALSE
FROM subscription_plans;

-- Exemplo: adicionar nova feature
INSERT INTO subscription_plan_config (subscription_plan_id, config_key, config_value, is_enabled)
SELECT id, 'allow_advanced_reports',
  NULL,
  CASE
    WHEN slug IN ('pro', 'premium') THEN TRUE
    ELSE FALSE
  END
FROM subscription_plans;
```

---

## 🔧 Implementação Backend

### 1. Models

#### `app/Models/SubscriptionPlan.php`
Implementado em: `app/Models/SubscriptionPlan.php`.

#### `app/Models/SubscriptionPlanConfig.php`
Implementado em: `app/Models/SubscriptionPlanConfig.php`.

#### `app/Models/UserSubscription.php`
Implementado em: `app/Models/UserSubscription.php`.

#### `app/Models/UserSubscriptionUsage.php`
Implementado em: `app/Models/UserSubscriptionUsage.php`.

### 2. Service para Gerenciar Limites

#### `app/Services/SubscriptionLimitService.php`

Implementado em: `app/Services/SubscriptionLimitService.php`.

### 3. Exception Customizada

#### `app/Exceptions/SubscriptionLimitExceededException.php`

Implementado em: `app/Exceptions/SubscriptionLimitExceededException.php`.

### 4. Middleware de Verificação

#### `app/Http/Middleware/CheckSubscriptionLimits.php`

Implementado em: `app/Http/Middleware/CheckSubscriptionLimits.php`.

#### Registrar Middleware no `Kernel.php` ou `bootstrap/app.php`

Implementado em: `bootstrap/app.php`.

### 5. Observers para Atualizar Contadores em Tempo Real

**Recomendação:** Use **Observers** para atualizar contadores automaticamente. É mais simples de manter e evolui naturalmente com a estrutura EAV.

#### `app/Observers/PortfolioObserver.php`

Implementado em: `app/Observers/PortfolioObserver.php`.

#### `app/Observers/CompositionObserver.php`

Implementado em: `app/Observers/CompositionObserver.php`.

#### `app/Observers/AccountObserver.php`

Implementado em: `app/Observers/AccountObserver.php`.

#### `app/Observers/ConsolidatedObserver.php`

Implementado em: `app/Observers/ConsolidatedObserver.php`.

#### `app/Observers/UserObserver.php` (Garante assinatura free automática)

Implementado em: `app/Observers/UserObserver.php`.

#### Registrar Observers no `AppServiceProvider`

Implementado em: `app/Providers/AppServiceProvider.php`.

#### Garantir Assinatura no AuthController (Login)

Implementado em: `app/Http/Controllers/Api/AuthController.php`.

### 6. Controllers

#### `app/Http/Controllers/Api/SubscriptionPlanController.php`

Implementado em: `app/Http/Controllers/Api/SubscriptionPlanController.php`.

#### `app/Http/Controllers/Api/UserSubscriptionController.php`

Implementado em: `app/Http/Controllers/Api/UserSubscriptionController.php`.

#### `app/Http/Controllers/Api/RevenueCatWebhookController.php`

Implementado em: `app/Http/Controllers/Api/RevenueCatWebhookController.php`.

### 7. Service para Webhooks do RevenueCat

#### `app/Services/RevenueCatWebhookService.php`

Implementado em: `app/Services/RevenueCatWebhookService.php`.

### 8. Resources

#### `app/Http/Resources/SubscriptionPlanResource.php`

Implementado em: `app/Http/Resources/SubscriptionPlanResource.php`.

#### `app/Http/Resources/UserSubscriptionResource.php`

Implementado em: `app/Http/Resources/UserSubscriptionResource.php`.

### 9. Rotas

#### `routes/api.php`

Implementado em: `routes/api.php`.

### 10. Config

#### `config/services.php`

Implementado em: `config/services.php`.

#### `.env`

```env
REVENUECAT_API_KEY=your_api_key_here
REVENUECAT_WEBHOOK_SECRET=your_webhook_secret_here
REVENUECAT_PUBLIC_APP_KEY=your_public_app_key_here
```

---

## 📱 Integração Mobile (React Native)

### 1. Instalar SDK do RevenueCat

```bash
npm install react-native-purchases
cd ios && pod install
```

### 2. Configurar RevenueCat

#### `services/revenuecat.ts`

```typescript
import Purchases from 'react-native-purchases';

const REVENUECAT_API_KEY = {
  ios: 'appl_xxxxxxxxxxxxx',
  android: 'goog_xxxxxxxxxxxxx',
};

export const configureRevenueCat = async (userId: string) => {
  const apiKey = Platform.OS === 'ios' ? REVENUECAT_API_KEY.ios : REVENUECAT_API_KEY.android;

  await Purchases.configure({ apiKey, appUserID: userId.toString() });

  console.log('RevenueCat configured for user:', userId);
};

export const getOfferings = async () => {
  try {
    const offerings = await Purchases.getOfferings();
    return offerings.current;
  } catch (error) {
    console.error('Error fetching offerings:', error);
    return null;
  }
};

export const purchasePackage = async (packageToPurchase: any) => {
  try {
    const { customerInfo } = await Purchases.purchasePackage(packageToPurchase);

    // Verifica entitlements ativos
    if (customerInfo.entitlements.active['pro']) {
      return { success: true, plan: 'investor' };
    } else if (customerInfo.entitlements.active['premium']) {
      return { success: true, plan: 'premium' };
    }

    return { success: false };
  } catch (error: any) {
    if (error.userCancelled) {
      return { success: false, reason: 'cancelled' };
    }

    console.error('Purchase error:', error);
    return { success: false, reason: 'error', error };
  }
};

export const restorePurchases = async () => {
  try {
    const customerInfo = await Purchases.restorePurchases();
    return customerInfo.entitlements.active;
  } catch (error) {
    console.error('Restore error:', error);
    return null;
  }
};

export const getCustomerInfo = async () => {
  try {
    const customerInfo = await Purchases.getCustomerInfo();
    return customerInfo;
  } catch (error) {
    console.error('Error getting customer info:', error);
    return null;
  }
};
```

### 3. Hook de Assinatura

#### `hooks/useSubscription.ts`

```typescript
import { useQuery, useMutation } from '@tanstack/react-query';
import { api } from '@/services/api';
import { getCustomerInfo } from '@/services/revenuecat';

export type SubscriptionLimits = {
  max_portfolios: number | null;
  max_compositions: number | null;
  max_positions: number | null;
  max_accounts: number | null;
};

export type SubscriptionFeatures = {
  allow_full_crossing: boolean;
  allow_composition_history: boolean;
  allow_category_analysis: boolean;
  allow_multi_portfolio_analysis: boolean;
};

export type SubscriptionUsage = {
  current_portfolios: number;
  current_compositions: number;
  current_positions: number;
  current_accounts: number;
  last_calculated_at: string;
};

export type UserSubscription = {
  id: number;
  plan: {
    name: string;
    slug: string;
    price_monthly: string;
  };
  limits: SubscriptionLimits;
  features: SubscriptionFeatures;
  usage?: SubscriptionUsage;
  status: string;
  is_active: boolean;
  is_trialing: boolean;
};

export const useSubscription = () => {
  const query = useQuery({
    queryKey: ['subscription', 'current'],
    queryFn: async () => {
      const response = await api.get('/subscription/current');
      return response.data.data as UserSubscription;
    },
  });

  const syncWithRevenueCat = useMutation({
    mutationFn: async () => {
      const customerInfo = await getCustomerInfo();
      // Backend sincroniza automaticamente via webhook, mas podemos forçar sync aqui se necessário
      return customerInfo;
    },
    onSuccess: () => {
      query.refetch();
    },
  });

  return {
    subscription: query.data,
    isLoading: query.isLoading,
    error: query.error,
    refetch: query.refetch,
    syncWithRevenueCat,
  };
};
```

### 4. Tela de Upgrade/Planos

#### `app/(tabs)/subscription.tsx`

```typescript
import React, { useEffect, useState } from 'react';
import { View, Text, ScrollView, TouchableOpacity, ActivityIndicator } from 'react-native';
import { useSubscription } from '@/hooks/useSubscription';
import { getOfferings, purchasePackage } from '@/services/revenuecat';
import { Feather } from '@expo/vector-icons';

export default function SubscriptionScreen() {
  const { subscription, isLoading, refetch } = useSubscription();
  const [offerings, setOfferings] = useState<any>(null);
  const [purchasing, setPurchasing] = useState(false);

  useEffect(() => {
    loadOfferings();
  }, []);

  const loadOfferings = async () => {
    const current = await getOfferings();
    setOfferings(current);
  };

  const handlePurchase = async (pkg: any) => {
    setPurchasing(true);
    try {
      const result = await purchasePackage(pkg);

      if (result.success) {
        Alert.alert('Sucesso!', 'Sua assinatura foi ativada com sucesso.');
        await refetch();
      } else if (result.reason === 'cancelled') {
        // Usuário cancelou
      } else {
        Alert.alert('Erro', 'Não foi possível processar a compra. Tente novamente.');
      }
    } finally {
      setPurchasing(false);
    }
  };

  if (isLoading) {
    return <LoadingState message="Carregando assinatura..." />;
  }

  return (
    <ScrollView style={styles.container}>
      <View style={styles.currentPlan}>
        <Text style={styles.currentPlanLabel}>Plano Atual</Text>
        <Text style={styles.currentPlanName}>{subscription?.plan.name}</Text>
        {subscription?.usage && (
          <View style={styles.usage}>
            <Text>Portfólios: {subscription.usage.current_portfolios}/{subscription.limits.max_portfolios ?? '∞'}</Text>
            <Text>Composições: {subscription.usage.current_compositions}/{subscription.limits.max_compositions ?? '∞'}</Text>
            <Text>Posições: {subscription.usage.current_positions}/{subscription.limits.max_positions ?? '∞'}</Text>
            <Text>Contas: {subscription.usage.current_accounts}/{subscription.limits.max_accounts ?? '∞'}</Text>
          </View>
        )}
      </View>

      {offerings?.availablePackages.map((pkg: any) => (
        <TouchableOpacity
          key={pkg.identifier}
          style={styles.planCard}
          onPress={() => handlePurchase(pkg)}
          disabled={purchasing}
        >
          <Text style={styles.planName}>{pkg.product.title}</Text>
          <Text style={styles.planPrice}>{pkg.product.priceString}/mês</Text>
          <Text style={styles.planDescription}>{pkg.product.description}</Text>

          {purchasing ? (
            <ActivityIndicator />
          ) : (
            <View style={styles.upgradeButton}>
              <Text style={styles.upgradeButtonText}>Assinar</Text>
            </View>
          )}
        </TouchableOpacity>
      ))}
    </ScrollView>
  );
}
```

### 5. Verificação de Features no Frontend (Apenas Exibição)

**IMPORTANTE:** Toda verificação de segurança é feita no **backend**. O frontend apenas exibe ou esconde informações baseado nas features, mas **NUNCA** deve ser usado como controle de acesso.

#### `hooks/useSubscriptionFeatures.ts`

```typescript
import { useSubscription } from './useSubscription';

export const useSubscriptionFeatures = () => {
  const { subscription } = useSubscription();

  const hasFullCrossingAccess = () => {
    return subscription?.features.allow_full_crossing ?? false;
  };

  const canViewCompositionHistory = () => {
    return subscription?.features.allow_composition_history ?? false;
  };

  const canViewCategoryAnalysis = () => {
    return subscription?.features.allow_category_analysis ?? false;
  };

  const canViewMultiPortfolioAnalysis = () => {
    return subscription?.features.allow_multi_portfolio_analysis ?? false;
  };

  return {
    hasFullCrossingAccess,
    canViewCompositionHistory,
    canViewCategoryAnalysis,
    canViewMultiPortfolioAnalysis,
    subscription,
  };
};
```

#### Exemplo: Tela de Crossing com Restrições (Apenas Visual)

```typescript
// app/(tabs)/(portfolios)/crossing/[id].tsx

const { hasFullCrossingAccess } = useSubscriptionFeatures();
const showFullData = hasFullCrossingAccess();

// No render:
{showFullData ? (
  <View>
    <Text>Lucro Total: R$ {totalProfit.toFixed(2)}</Text>
    <Text>Valor Consolidado: R$ {totalBalance.toFixed(2)}</Text>
    <Text>Rentabilidade: {profitPercentage.toFixed(2)}%</Text>
  </View>
) : (
  <View style={styles.premiumFeature}>
    <Feather name="lock" size={24} color={theme.colors.textSecondary} />
    <Text style={styles.premiumTitle}>Recurso Premium</Text>
    <Text style={styles.premiumDescription}>
      Faça upgrade para ver dados completos de lucro e rentabilidade
    </Text>
    <Button
      onPress={() => router.push('/(tabs)/subscription')}
      label="Ver Planos"
      preset="upgrade"
    />
  </View>
)}
```

**Nota:** O backend (através de `CrossingService` e Policies) SEMPRE valida permissões antes de retornar dados sensíveis. O frontend apenas melhora a UX escondendo visualmente o que o usuário não pode acessar.

---

## 🔄 Fluxo de Assinatura

### Fluxo Completo

1. **Novo Usuário (Cadastro):**
   - Usuário se registra
   - `UserObserver::created()` dispara automaticamente
   - Backend cria assinatura "Gratuito" com `ends_at = NULL`
   - `UserSubscriptionUsage` é criado com contadores zerados

2. **Usuário Antigo (Primeiro Login Pós-Implementação):**
   - Usuário faz login
   - `AuthController::login()` ou `AuthController::google()` chama `ensureUserHasSubscription()`
   - Se não tiver assinatura → Cria assinatura "Gratuito" automaticamente
   - `UserSubscriptionUsage` é criado

3. **Usuário Usa App:**
   - Cria portfólio → Observer incrementa `current_portfolios` automaticamente
   - Middleware verifica limite antes de criar (`subscription.limit:portfolio`)
   - Service consulta `UserSubscriptionUsage` + `limits_snapshot`
   - Se atingiu limite → Retorna erro 403 com mensagem
   - Se OK → Controller cria → Observer incrementa contador

4. **Usuário Decide Fazer Upgrade:**
   - Vai para tela de planos (`GET /subscription-plans`)
   - Visualiza limites e features de cada plano
   - Seleciona plano (Iniciante, Pro ou Premium)
   - RevenueCat processa compra na loja (App Store ou Play Store)

5. **RevenueCat Envia Webhook:**
   - Webhook: `INITIAL_PURCHASE`
   - Backend valida assinatura HMAC
   - Cancela assinatura "Gratuito" atual (`status = canceled`, `ends_at = now()`)
   - Cria nova assinatura do plano comprado
   - Snapshot de limites/features é capturado do plano
   - `UserSubscriptionUsage` mantém contadores (não recalcula)

6. **Usuário Volta ao App:**
   - App sincroniza com RevenueCat (`getCustomerInfo()`)
   - Fetch `GET /subscription/current` retorna novo plano
   - Limites aumentam, features são liberadas
   - Frontend atualiza UI

7. **Renovação Automática:**
   - Loja processa renovação mensal/anual
   - RevenueCat envia webhook: `RENEWAL`
   - Backend atualiza `renews_at` e mantém `status = active`

8. **Cancelamento:**
   - Usuário cancela na loja
   - RevenueCat envia webhook: `CANCELLATION`
   - Backend marca assinatura como `status = canceled`
   - Assinatura permanece ativa até `ends_at`
   - Quando expirar (`ends_at` < now):
     - Backend cria nova assinatura "Gratuito" automaticamente
     - `ends_at = NULL` (nunca expira)
     - Usuário volta aos limites do plano free

9. **Expiração (Pagamento Falhou):**
   - RevenueCat envia webhook: `EXPIRATION`
   - Backend marca assinatura como `status = expired`
   - Backend cria assinatura "Gratuito" automaticamente
   - Usuário é rebaixado para plano free

---

## ✅ Checklist de Implementação

### Fase 1: Database
- [x] Criar migration para `subscription_plans`
- [x] Criar migration para `subscription_plan_config`
- [x] Criar migration para `user_subscriptions`
- [x] Criar migration para `user_subscription_usage`
- [x] Criar migration para `revenuecat_webhook_logs`
- [ ] Rodar migrations
- [x] Seed dos planos (Free, Investor, Premium)

### Fase 2: Backend Core
- [x] Criar Models (`SubscriptionPlan`, `SubscriptionPlanConfig`, `UserSubscription`, `UserSubscriptionUsage`, `RevenueCatWebhookLog`)
- [x] Criar `SubscriptionLimitService`
- [x] Criar `RevenueCatWebhookService`
- [x] Criar Exception `SubscriptionLimitExceededException`
- [x] Criar Middleware `CheckSubscriptionLimits`
- [x] Criar Observers (User, Portfolio, Composition, Account, Consolidated)
- [x] Registrar Observers no `AppServiceProvider`

### Fase 3: API
- [x] Criar Resources (SubscriptionPlan, UserSubscription)
- [x] Criar Controllers (SubscriptionPlan, UserSubscription, RevenueCatWebhook)
- [x] Adicionar rotas em `api.php`
- [x] Aplicar middleware de limite em rotas de criação
- [x] Modificar `CrossingService` para respeitar `allow_full_crossing`
- [ ] Testar endpoints

### Fase 4: RevenueCat Setup
- [ ] Criar conta no RevenueCat
- [ ] Configurar produtos (datagrana_investor_monthly, datagrana_premium_monthly)
- [ ] Configurar entitlements (pro, premium)
- [ ] Configurar ofertas
- [ ] Obter API Keys (iOS, Android, Backend)
- [ ] Configurar webhook URL
- [ ] Adicionar variáveis no `.env`

### Fase 5: Mobile Integration
- [ ] Instalar `react-native-purchases`
- [ ] Configurar SDK no app
- [ ] Criar `services/revenuecat.ts`
- [ ] Criar `hooks/useSubscription.ts`
- [ ] Criar `hooks/useSubscriptionLimits.ts`
- [ ] Criar tela de planos/upgrade
- [ ] Aplicar verificações nas telas (Crossing, Create Portfolio, etc)
- [ ] Testar fluxo completo

### Fase 6: Testing
- [ ] Testar criação de assinatura gratuita
- [ ] Testar limites (portfolios, composições, posições, contas)
- [ ] Testar compra via RevenueCat (Sandbox)
- [ ] Testar webhook INITIAL_PURCHASE
- [ ] Testar webhook RENEWAL
- [ ] Testar webhook CANCELLATION
- [ ] Testar webhook EXPIRATION
- [ ] Testar restore purchases
- [ ] Testar sincronização mobile ↔ backend

### Fase 7: Production
- [ ] Configurar RevenueCat para produção
- [ ] Configurar produtos nas lojas (App Store Connect, Google Play Console)
- [ ] Atualizar `.env` de produção
- [ ] Deploy backend
- [ ] Deploy mobile
- [ ] Monitorar webhooks
- [ ] Monitorar logs

---

## 📊 Monitoramento e Métricas

### Queries Úteis

```sql
-- Assinaturas ativas por plano
SELECT
  plan_name,
  COUNT(*) as total_users,
  SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users
FROM user_subscriptions
GROUP BY plan_name;

-- Revenue mensal estimado
SELECT
  SUM(price_monthly) as monthly_revenue
FROM user_subscriptions
WHERE status = 'active' AND is_paid = true;

-- Usuários que atingiram limites
SELECT
  u.email,
  us.plan_name,
  usu.current_portfolios,
  us.max_portfolios,
  usu.current_active_positions,
  us.max_active_positions
FROM users u
JOIN user_subscriptions us ON u.id = us.user_id
JOIN user_subscription_usage usu ON us.id = usu.user_subscription_id
WHERE
  (usu.current_portfolios >= us.max_portfolios OR us.max_portfolios IS NULL)
  OR (usu.current_active_positions >= us.max_active_positions OR us.max_active_positions IS NULL);

-- Taxa de conversão (Free → Paid)
SELECT
  (SELECT COUNT(*) FROM user_subscriptions WHERE plan_slug != 'free' AND status = 'active') * 100.0 /
  (SELECT COUNT(*) FROM user_subscriptions WHERE status = 'active') as conversion_rate;
```

---

## 🔗 Referências

- **RevenueCat Docs:** https://docs.revenuecat.com/
- **React Native Purchases:** https://github.com/RevenueCat/react-native-purchases
- **Webhook Events:** https://docs.revenuecat.com/docs/webhooks
- **App Store In-App Purchase:** https://developer.apple.com/in-app-purchase/
- **Google Play Billing:** https://developer.android.com/google/play/billing

---

## 💡 Observações Importantes

1. **Snapshot de Limites:** Quando usuário assina, copiamos os limites do plano para a `user_subscription`. Isso garante que mudanças futuras nos planos não afetem assinaturas existentes.

2. **Assinatura Gratuita Automática:** Todo usuário novo recebe automaticamente o plano gratuito. Isso simplifica a lógica e garante que sempre há uma assinatura ativa.

3. **Verificação Dupla:** Limites são verificados tanto no backend (obrigatório) quanto no frontend (UX). O backend sempre prevalece.

4. **RevenueCat como Source of Truth:** Para assinaturas pagas, o RevenueCat é a fonte da verdade. O backend sincroniza via webhooks.

5. **Graceful Degradation:** Quando assinatura expira, usuário volta automaticamente para o plano gratuito (não perde acesso total).

6. **Observers vs Cron Jobs:** Usamos Observers para atualizar contadores em tempo real. Alternativamente, pode-se usar cron job diário para recalcular, mas Observers são mais precisos.

7. **Feature Flags:** Campos booleanos (`allow_*`) funcionam como feature flags, facilitando adicionar/remover features dos planos.

8. **Compatibilidade:** A estrutura mantém `gateway_id` e outros campos para compatibilidade com sistema de pagamento existente (Asaas), mas prioriza RevenueCat para mobile.

---

**Fim do Roadmap V7**
