# Ads (AdMob) - Configuração e Métricas

Este documento resume a configuração de consentimento, depuração e os principais indicadores para acompanhar a saúde de ads.

**Base oficial (AdMob/Google Mobile Ads)**
1. UMP (User Messaging Platform) deve ser chamado em todo app launch para atualizar consentimento, exibir formulário se necessário, e somente depois permitir requisições de anúncio. citeturn1search1
2. `canRequestAds` deve ser verificado antes de solicitar anúncios. citeturn1search1
3. Ad Inspector deve ser usado em dispositivos de teste para validar integração e diagnosticar problemas de fill e mediations. citeturn0search1turn0search4

**O que já foi implementado no app**
1. Fluxo UMP (requestInfoUpdate + loadAndShowConsentFormIfRequired) com gating por `canRequestAds`.
2. `openAdInspector` disponível no contexto de ads para depuração.
3. `showPrivacyOptionsForm` disponível no contexto para opções de privacidade quando requerido.
4. `setRequestConfiguration` para registrar test devices e `tagForUnderAgeOfConsent`.
5. Remoção do `requestNonPersonalizedAdsOnly` fixo para não degradar eCPM quando o consentimento permite anúncios personalizados.

**Variáveis de ambiente (app)**
1. `EXPO_PUBLIC_ADMOB_BANNER_ID` = Ad Unit ID do banner.
2. `EXPO_PUBLIC_ADMOB_INTERSTITIAL_ID` = Ad Unit ID do interstitial.
3. `EXPO_PUBLIC_ADMOB_TEST_DEVICE_IDS` = IDs separados por vírgula.
4. `EXPO_PUBLIC_ADMOB_DEBUG_GEOGRAPHY` = `eea` | `not_eea` | `regulated_us` | `other` | `disabled`.
5. `EXPO_PUBLIC_ADMOB_TAG_UNDER_AGE` = `true` | `false`.

**Checklist AdMob Console**
1. Configure mensagens em Privacy & messaging (UMP) e publique. citeturn1search1
2. Cadastre dispositivos de teste e configure gesto do Ad Inspector. citeturn0search1
3. Abra o Ad Inspector e verifique a cadeia de demanda e sinais de privacidade. citeturn0search2

**Erro comum: “Publisher misconfiguration: no form(s) configured for the input app ID”**
1. O UMP não encontrou **nenhum formulário publicado** para o App ID informado.
2. Enquanto não houver formulário publicado, o SDK falhará ao carregar o consentimento.

**Tutorial rápido: configurar UMP no AdMob**
1. Acesse **AdMob Console** → **Privacy & messaging**.
2. Crie uma **mensagem de consentimento** (EEA e/ou Estados regulados nos EUA, conforme seu público).
3. Associe a mensagem ao **App ID** correto do app (o mesmo usado no projeto).
4. **Publicar** a mensagem.
5. Reabra o app e valide no **Ad Inspector**. citeturn0search1turn0search4

**Métricas-chave**
1. Match rate = razão entre ad requests com match e o total de requests. citeturn0search2
2. Show rate = impressões / matched requests. citeturn0search2
3. eCPM observado = média estimada de eCPM (métricas do AdMob API). citeturn0search2

**Notas de operação**
1. Se o match rate cair, valide via Ad Inspector se há no‑fill, bloqueios por consentimento ou configuração inválida. citeturn0search1turn0search4
2. Para melhorar eCPM, certifique-se de que o consentimento permite anúncios personalizados quando aplicável. citeturn1search1

## Estratégia recomendada: receita sem irritar

Objetivo: maximizar receita mantendo fricção baixa no fluxo principal.

### Regras atuais no app (diagnóstico)
1. `Interstitial` por clique: mostra ao atingir `25` cliques rastreados.
2. `Interstitial` por tempo: timer de `6` minutos.
3. `Interstitial` ao retornar do background: após `2` minutos fora do app.
4. Contagem de clique é ampla: vários botões de UI usam `TrackedTouchableOpacity`.
5. `Banner` em listas com densidade alta em algumas telas: topo + inserção recorrente a cada `5` itens.

### Riscos atuais
1. Interstitial pode aparecer em momento de alta intenção (edição/salvamento/navegação curta).
2. Retorno de background com trigger agressivo pode parecer anúncio “inesperado”.
3. Frequência de banners em listas longas aumenta fadiga visual.

### Plano recomendado (faseado)

#### Fase 1 (rápida, baixo risco)
1. Subir `CLICK_THRESHOLD` de `25` para `40`.
2. Subir `TIME_THRESHOLD` de `6` para `8-10` minutos.
3. Ajustar inserção de banner de cada `5` para cada `8` itens nas listas principais.
4. Evitar banner em estados vazios/erro.

#### Fase 2 (controle fino de UX)
1. Introduzir cooldown global de interstitial: mínimo `180s` entre exibições.
2. Introduzir warmup de sessão: não exibir interstitial nos primeiros `90s`.
3. Definir cap por sessão: máximo `3` interstitials.
4. Definir cap diário: máximo `6` interstitials por usuário.
5. Limitar exibição de interstitial a “quebras naturais” (fim de ação, troca de contexto), evitando formulário aberto/modal.

#### Fase 3 (otimização orientada a dados)
1. Executar A/B por `14` dias:
2. Grupo A: regra atual.
3. Grupo B: regras das Fases 1 e 2.
4. Critério de decisão: `ARPDAU`, `eCPM`, impressões por DAU, retenção D1/D7, abandono após ad, duração de sessão.

### Checklist de implementação
1. Externalizar thresholds/caps em config remota ou env para ajustes sem release.
2. Registrar telemetria de cada trigger (`click`, `timer`, `background`) com timestamp e tela.
3. Garantir que `showAd()` respeite cooldown/caps antes de exibir.
4. Revisar telas com maior densidade de banner (Posições, Vendas, Crossing, Carteira detalhe).
5. Validar policy/UX em QA: sem interstitial em fluxo crítico.

### Padrão de decisão
1. Se `ARPDAU` subir e retenção ficar estável, manter configuração nova.
2. Se `ARPDAU` subir com queda relevante de retenção, reduzir agressividade (caps e triggers).
3. Se retenção subir sem perda relevante de receita, promover padrão como baseline.
