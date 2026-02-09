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
