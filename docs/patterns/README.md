# Padrões e Convenções - Documentação

**Objetivo**: Garantir código escalável, legível, seguro e manutenível em todos os projetos.

---

## 📚 Documentos Disponíveis

### Backend (Laravel)

1. **[controllers.md](./controllers.md)** - Padrões de Controllers e API
   - Estrutura de controllers
   - Escopo de segurança
   - Respostas padronizadas
   - Controllers Admin

2. **[models.md](./models.md)** - Padrões de Models e Relacionamentos
   - Convenções de nomenclatura
   - Relacionamentos tipados
   - Fillable, casts e propriedades
   - Pivot tables

3. **[resources.md](./resources.md)** - Padrões de API Resources
   - Transformação de dados
   - Campos condicionais
   - Related resources
   - Collections customizadas

4. **[tests.md](./tests.md)** - Padrões de Testes
   - Estrutura de testes
   - Nomenclatura
   - Assertions comuns
   - Traits reutilizáveis

5. **[endpoint-creation-standards.md](./endpoint-creation-standards.md)** - Checklist de Criação
   - Arquitetura em camadas
   - Fluxo de desenvolvimento
   - Checklist completo

### Frontend (Vue + Inertia)

6. **[frontend-vue-inertia.md](./frontend-vue-inertia.md)** - Padrões Frontend
   - Estrutura de pastas
   - Convenções de nomenclatura
   - shadcn-vue Components
   - Composables (nativos Inertia + customizados)
   - Types e Interfaces
   - Boas práticas
   - Performance
   - Acessibilidade

---

## 🎯 Princípios Fundamentais

### 1. Separação de Responsabilidades
- **Controllers**: Orquestração de requests/responses
- **Services**: Lógica de negócio complexa
- **Models**: Acesso a dados e relacionamentos
- **Resources**: Transformação de dados para API
- **Requests**: Validação de entrada
- **Policies**: Autorização

### 1.1 Formato de Dados
- ✅ Respostas da API usam `snake_case` em todas as chaves

### 2. Segurança em Primeiro Lugar
- ✅ Sempre validar propriedade de recursos (escopo por usuário)
- ✅ Usar Form Requests para validação
- ✅ Sanitizar inputs
- ✅ Implementar rate limiting
- ✅ Usar Policies para autorização
- ✅ Nunca expor dados sensíveis em Resources

### 3. Código Limpo e Testável
- ✅ Type hints completos (PHP e TypeScript)
- ✅ Nomenclatura consistente e descritiva
- ✅ Funções pequenas e focadas
- ✅ Testes para todas as features
- ✅ Documentação inline quando necessário

### 4. Performance
- ✅ Eager loading de relacionamentos
- ✅ Índices em campos de busca frequente
- ✅ Cache estratégico
- ✅ Lazy loading de componentes pesados
- ✅ Debounce em inputs de busca

### 5. Acessibilidade
- ✅ Labels em todos os inputs
- ✅ ARIA attributes quando necessário
- ✅ Navegação por teclado
- ✅ Estados de loading visíveis
- ✅ Mensagens de erro claras

---

## 🚀 Quick Start

### Criar Novo Endpoint (Backend)

```bash
# 1. Migration e Model
php artisan make:model ResourceName -m

# 2. Form Requests
php artisan make:request StoreResourceRequest
php artisan make:request UpdateResourceRequest

# 3. Resource
php artisan make:resource ResourceResource

# 4. Controller
php artisan make:controller Api/ResourceController

# 5. Tests
php artisan make:test Api/ResourceTest

# 6. Policy (opcional)
php artisan make:policy ResourcePolicy
```

**Checklist completo**: Ver [endpoint-creation-standards.md](./endpoint-creation-standards.md)

---

### Criar Novo Componente (Frontend)

```bash
# Estrutura recomendada
resources/js/components/
├── common/           # Componentes genéricos
│   └── BaseButton.vue
├── forms/           # Componentes de formulário
│   └── ResourceForm.vue
├── layout/          # Componentes de layout
│   └── AppSidebar.vue
└── ui/              # Componentes de UI
    └── Modal.vue
```

**Guia completo**: Ver [frontend-vue-inertia.md](./frontend-vue-inertia.md)

---

## 📊 Matriz de Decisão

### Quando usar cada padrão

| Cenário | Solução | Documento |
|---------|---------|-----------|
| Criar endpoint API | Controller + Request + Resource | [endpoint-creation-standards.md](./endpoint-creation-standards.md) |
| Lógica de negócio complexa | Service Class | [IMPROVEMENTS-PROPOSAL.md](./IMPROVEMENTS-PROPOSAL.md#12-extrair-lógica-de-negócio-para-services) |
| Validação customizada | Form Request | [endpoint-creation-standards.md](./endpoint-creation-standards.md) |
| Autorização | Policy | [IMPROVEMENTS-PROPOSAL.md](./IMPROVEMENTS-PROPOSAL.md#51-adicionar-authorize-com-policies) |
| Transformar dados API | API Resource | [resources.md](./resources.md) |
| Operação em banco | Model Scope | [IMPROVEMENTS-PROPOSAL.md](./IMPROVEMENTS-PROPOSAL.md#21-adicionar-scopes-reutilizáveis) |
| Auditoria automática | Model Observer | [IMPROVEMENTS-PROPOSAL.md](./IMPROVEMENTS-PROPOSAL.md#23-adicionar-model-observers-para-auditoria) |
| Lógica reutilizável (Vue) | Composable | [frontend-vue-inertia.md](./frontend-vue-inertia.md#composables) |
| Componente genérico (Vue) | Base Component | [frontend-vue-inertia.md](./frontend-vue-inertia.md#2-componentes-genéricos) |
| Formulário complexo (Vue) | Form Builder | [frontend-vue-inertia.md](./frontend-vue-inertia.md#22-form-builder) |

---

## ✅ Code Review Checklist

### Backend

- [ ] Controller usa BaseController
- [ ] Form Requests implementados (Store/Update)
- [ ] Escopo de segurança (user_id) implementado
- [ ] Resource implementado para transformação
- [ ] Type hints completos
- [ ] Testes cobrindo happy path e casos de erro
- [ ] Não há N+1 queries (usar with())
- [ ] Mensagens de erro são claras

### Frontend

- [ ] Componente usa `<script setup>` com TypeScript
- [ ] Props e Emits estão tipados
- [ ] Usa Composition API (não Options API)
- [ ] Todos v-for têm :key único
- [ ] Inputs têm labels adequadas
- [ ] Loading states são visíveis
- [ ] Erros são exibidos claramente
- [ ] Sem console.log em produção
- [ ] Formatação ESLint + Prettier aplicada

---

## 🔄 Evolução Contínua

### Processo de Atualização

1. **Propor mudança**: Criar issue ou PR com justificativa
2. **Revisar impacto**: Avaliar breaking changes
3. **Atualizar docs**: Manter documentação sincronizada
4. **Comunicar**: Notificar equipe sobre mudanças
5. **Migrar gradualmente**: Não quebrar código existente

### Versionamento de Padrões

- **Major**: Mudanças breaking (ex: migrar Options API → Composition API)
- **Minor**: Novas funcionalidades (ex: novo composable)
- **Patch**: Correções e melhorias (ex: typos, exemplos melhores)

---

## 📞 Suporte

### Dúvidas Frequentes

**P: Posso usar Options API no Vue?**
R: Não. Todos os novos componentes devem usar Composition API com `<script setup>`.

**P: Preciso criar Service para toda lógica?**
R: Não. Apenas para lógica complexa (>30 linhas) ou reutilizável.

**P: Posso pular testes?**
R: Não. Todo endpoint/feature deve ter testes cobrindo casos de sucesso e falha.

**P: Como escolher entre ref e reactive?**
R: Use `ref` para primitivos, `reactive` para objetos.

**P: Devo criar Resource para todo Model?**
R: Sim. Sempre use Resources para transformar dados de API, nunca retorne Model direto.

---

## 📝 Contribuindo

Para sugerir melhorias nestes padrões:

1. Leia a documentação completa
2. Verifique se não foi proposto antes
3. Crie exemplo de código demonstrando benefício
4. Considere impacto em código existente
5. Proponha plano de migração se breaking change

---

## 📚 Referências Externas

### Laravel
- [Laravel Documentation](https://laravel.com/docs)
- [Laravel Best Practices](https://github.com/alexeymezenin/laravel-best-practices)
- [Spatie Guidelines](https://guidelines.spatie.be/laravel-php/)

### Vue
- [Vue 3 Documentation](https://vuejs.org/)
- [Vue Composition API](https://vuejs.org/guide/extras/composition-api-faq.html)
- [VueUse](https://vueuse.org/) - Collection of composables

### Inertia
- [Inertia.js Documentation](https://inertiajs.com/)
- [Inertia.js Best Practices](https://inertiajs.com/best-practices)

### TypeScript
- [TypeScript Handbook](https://www.typescriptlang.org/docs/)
- [Type Challenges](https://github.com/type-challenges/type-challenges)

---

**Última atualização**: 2025-12-29
**Versão**: 2.1
**Mantenedores**: Dev Team

### Changelog v2.1 (2025-12-29)
- ✅ Revisão completa de recursos nativos do Inertia v2
- ✅ Documentação atualizada para priorizar `useForm`, `<Form>` e `usePage` nativos
- ✅ Separação clara entre composables nativos e customizados
- ✅ Remoção de duplicação com recursos já fornecidos pelo Inertia
- ✅ Identificados 20 componentes shadcn-vue já instalados
- ✅ Documentação atualizada para priorizar componentes shadcn-vue
- ✅ Listados componentes prioritários para instalação (Table, Form, Select, etc)
