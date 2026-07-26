# Auto Clicker (macOS)

Auto clicker que fixa um clique num ponto do ecrã **sem prender o rato**:
no modo "Segundo plano" o clique é entregue diretamente à app que estava
debaixo do ponto capturado, por isso o cursor não se move e podes continuar
a usar o rato noutras janelas.

## Como usar

```bash
./run.sh
```

1. Carrega em **"Capturar alvo (3 s)"** e, durante a contagem, põe o cursor
   em cima do sítio onde queres os cliques. A app guarda a posição e a
   aplicação alvo. (Alternativa: **Fn+F6** — nos teclados Mac o F6 sozinho
   é tecla de brilho/media, é preciso o Fn.)
2. Ajusta o intervalo (ms), o botão (esquerdo/direito) e o modo.
3. Carrega em **"Iniciar"** (ou **Fn+F7**) para começar e parar.

## Modos

- **Real (predefinido)** — clique de sistema verdadeiro, indistinguível de
  um clique teu: o cursor salta para o alvo, clica e volta imediatamente
  para onde estava. Continuas a usar o rato; só vês um piscar breve do
  cursor a cada clique. Nota: como é um clique real, a janela alvo ganha
  foco a cada clique — se estiveres a escrever noutra janela, o foco muda.
- **Segundo plano** — envia os eventos só ao processo da app alvo
  (`CGEventPostToPid`). O cursor não mexe nem rouba o foco, mas algumas
  apps (sobretudo jogos) ignoram eventos entregues assim.

  **Importante:** os browsers (Chrome, Safari, Firefox, …) ignoram cliques
  enviados em segundo plano — para páginas web usa o modo **Real**. A app
  avisa quando fixas um alvo num browser neste modo.

  Em qualquer dos modos aparece uma **seta azul** que segue o cursor:
  - prime **1** para a fixar no ponto onde está (fica pregada no alvo);
  - prime **1** outra vez para a soltar e reposicionar;
  - **Iniciar** passa a clicar no ponto da seta.

  (Enquanto os cliques estão ativos, a tecla 1 é ignorada — podes escrever
  normalmente. O macOS não permite pintar o cursor real de azul, por isso a
  seta azul é uma sobreposição que o acompanha.)

## Permissões (obrigatório — sem isto os cliques não fazem nada)

O macOS descarta silenciosamente cliques de processos sem permissão de
Acessibilidade: a app parece funcionar e o contador sobe, mas nada acontece.

No primeiro arranque a app pede a permissão e, enquanto faltar, mostra um
aviso vermelho com um botão para abrir as Definições. Em **Definições do
Sistema → Privacidade e Segurança**, adiciona o **Terminal** (ou a app de
onde corres o script) a:

- **Acessibilidade** — necessária para enviar cliques;
- **Monitorização de Entrada** — necessária para os atalhos Fn+F6/Fn+F7.

Depois de dar as permissões, **fecha e volta a abrir a app** (e, se ainda
não funcionar, fecha e reabre também o Terminal).

## Paragem de emergência (importante!)

Se os cliques ficarem fora de controlo, tens sempre três saídas:

1. **Atira o cursor para o canto superior esquerdo do ecrã** — para
   instantaneamente. Funciona sempre, mesmo sem permissões de teclado.
2. **ESC** — para os cliques (precisa da permissão de Monitorização de
   Entrada).
3. **Máx. cliques** — define um limite (ex.: 100) e a app para sozinha;
   o contador recomeça em cada "Iniciar".

Proteções automáticas no modo Real: a app nunca clica enquanto tens o botão
físico do rato premido (evita cliques a meio de arrastos e o estado de
"botão preso"), e o intervalo mínimo é 50 ms para o cursor ter sempre tempo
de ir e voltar.

## Notas

- O intervalo mínimo é 10 ms (~100 cliques/segundo).
- Se a app alvo fechar, captura o alvo de novo com F6.
- Requer o `venv/` incluído (pyobjc-framework-Quartz + pynput); para
  recriar: `python3 -m venv venv && venv/bin/pip install pyobjc-framework-Quartz pynput`.
