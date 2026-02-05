# Comparação de Redes de Anúncios Mobile

Pesquisa realizada em Fevereiro/2026 para avaliar as melhores opções de mediação de anúncios para o Datagrana App.

---

## Market Share (2025)

| Rede | Market Share Android |
|------|---------------------|
| **Google AdMob** | 28% |
| **AppLovin** | 24% |
| **Unity Ads** | 13% |
| Meta Audience Network | ~8% |

> AppLovin e Google AdMob juntos representam 52% do mercado.

---

## eCPM (Receita por 1000 impressões)

| Rede | eCPM | Melhor para |
|------|------|-------------|
| **AppLovin** | Alto | Interstitial, Rewarded, Playable |
| **Meta Audience Network** | Alto | Apps sociais, simulação |
| **Unity Ads** | Médio-Alto | Games, Rewarded Video |

### Detalhes por rede:

- **AppLovin**: Suporta vídeo, interstitial e playable ads com altas taxas de engajamento. Atrai anunciantes premium, garantindo eCPMs mais altos.
- **Meta Audience Network**: Com sua vasta base de anunciantes, oferece um dos eCPMs mais altos do mercado.
- **Unity Ads**: Oferece eCPMs premium para tráfego Tier-1 (US, UK, Canadá) e forte presença em mercados emergentes.

---

## Fill Rate (Taxa de preenchimento)

| Rede | Fill Rate | Observação |
|------|-----------|------------|
| **Google AdMob** | ~100% | Acesso a milhões de anunciantes via Google Ads |
| **AppLovin** | 90-98% | Ampla base de anunciantes |
| **Unity Ads** | 80-95% | Melhor em games |
| **Meta Audience Network** | 70-85% | Depende do público-alvo |

---

## Facilidade de Integração

| Rede | Dificuldade | Notas |
|------|-------------|-------|
| **Unity Ads** | Fácil | Integração nativa com Unity/React Native |
| **AppLovin** | Média | SDK robusto, mais configuração |
| **Meta Audience Network** | Complexa | Requer conta Business verificada |

---

## Pontos Fortes de Cada Plataforma

### AppLovin MAX
- Domina o mercado em 2026, especialmente para iOS
- Plataforma de mediação MAX é padrão ouro para lances em tempo real
- Usa machine learning para prever engajamento e aumentar eCPM

### Unity Ads
- Fácil integração se o app/game usa Unity
- Anúncios rewarded e playable mantêm retenção alta
- Compromisso de 100% in-app-bidding até final de 2025

### Meta Audience Network
- Excelente para apps de simulação e sociais
- Usa dados sociais para anúncios altamente segmentados
- Melhor para públicos específicos

---

## Melhores Casos de Uso (2026)

| Tipo de App | Redes Recomendadas |
|-------------|-------------------|
| Hyper-Casual & Puzzle | AdMob + AppLovin |
| RPG & Strategy | Unity Ads + InMobi |
| Simulação & Social | Meta Audience Network + InMobi |
| **Finanças (Datagrana)** | **AdMob + AppLovin** |

---

## Recomendação para Datagrana App

### Configuração Atual
- **AdMob** como rede principal (já configurado)

### Configuração Recomendada (com Mediação)
1. **AdMob** - Rede principal (maior fill rate)
2. **AppLovin** - Fallback (melhor eCPM)
3. **Meta Audience Network** - Terceira opção (se tiver conta Business)

### Como Configurar Mediação no AdMob

1. Acesse [AdMob Console](https://admob.google.com)
2. Vá em **Mediação** → **Criar grupo de mediação**
3. Adicione as redes:
   - AdMob (principal)
   - AppLovin (fallback)
4. Configure lances em cascata (waterfall)

---

## Métricas Importantes

### Taxa de Correspondência (Match Rate)
- Porcentagem de solicitações que recebem um anúncio
- Apps novos podem ter taxa baixa na primeira semana
- Meta: 85-95%

### Estratégia de Retry Implementada
O Datagrana App implementa retry com backoff exponencial:

```
Tentativa 1 → Aguarda 3s
Tentativa 2 → Aguarda 5s
Tentativa 3 → Aguarda 10s
Tentativa 4 → Aguarda 30s
Tentativa 5 → Aguarda 60s
```

### Interstitial Ads
- Exibido a cada **6 minutos** OU **25 cliques**
- Também exibe quando app volta do background

---

## IDs de Anúncios

### Produção (eas.json - profile production)
```
Banner: ca-app-pub-4102839356174697/7791646790
Interstitial: ca-app-pub-4102839356174697/6067501585
```

### Teste (desenvolvimento local)
```
Banner: ca-app-pub-3940256099942544/6300978111
Interstitial: ca-app-pub-3940256099942544/1033173712
```

> **Importante**: IDs de produção só funcionam em apps assinados e publicados na Play Store.

---

## Referências

- [Ad Monetization Benchmark Report 2025 - Tenjin](https://tenjin.com/blog/ad-mon-gaming-2025/)
- [Top Advertising Networks 2026 - The Game Marketer](https://www.thegamemarketer.com/insight-posts/top-advertising-networks-for-mobile-gaming-apps)
- [Unity LevelPlay vs AppLovin MAX 2025 - Bidlogic](https://bidlogic.io/2025/08/29/unity-levelplay-vs-applovin-max-2025-how-to-choose-the-best-ad-mediation-platform/)
- [Top 10 App Monetization Platforms 2026 - adjoe](https://adjoe.io/blog/top-10-app-monetization-platforms/)
- [Best Mobile Ad Networks 2025 - Traffic Cardinal](https://en.trafficcardinal.com/post/10-best-mobile-ad-networks-for-publishers-in-2025-maximize-revenue-growth)

---

*Última atualização: Fevereiro 2026*
