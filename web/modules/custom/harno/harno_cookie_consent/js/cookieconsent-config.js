
CookieConsent.run({

  root: 'body',
  autoclear_cookies: true,
  page_scripts: true,
  mode: 'opt-out',

  cookie: {
    name: 'cc_cookie',
    domain: ( window.DOMAIN_NAME || window.location.hostname ),
    path: '/',
    sameSite: "Strict",
    expiresAfterDays: 0,
    force_consent: true,
  },
    onConsent: ({cookie}) => {
      if (cookie.categories.includes('analytics')) {
        if (cookie.services.analytics.includes('matomo')){
          enableMatomoAnalytics();
        }
        else {
          disableMatomoAnalytics();
        }
        if (cookie.services.analytics.includes('ga')){
          enableAnalytics();
        }
        else {
          disableAnalytics();
        }

      } else {
        disableAnalytics();
        disableMatomoAnalytics();
      }
  },

  onChange: function (cookie, changed_preferences) {
  },

  // https://cookieconsent.orestbida.com/reference/configuration-reference.html#guioptions
  guiOptions: {
    consentModal: {
      layout: 'cloud inline',
      position: 'bottom center',
      equalWeightButtons: true,
      flipButtons: false
    },
    preferencesModal: {
      layout: 'box',
      equalWeightButtons: true,
      flipButtons: false
    }
  },

  categories: {
    necessary: {
      enabled: true,  // this category is enabled by default
      readOnly: true  // this category cannot be disabled
    },
    analytics: {
      autoClear: {
        cookies: [
          {
            name: /^(_ga|_gid)/
          },
          {
            name: /^_pk/
          },
          {
            name: '_pk'
          },
        ]
      },

      // https://cookieconsent.orestbida.com/reference/configuration-reference.html#category-services

      services: {
        ga: {
          label: 'Google Analytics',
          onAccept: () => {enableAnalytics();},
          onReject: () => {disableAnalytics();}
        },

        matomo: {
          label: 'Matomo',
          onAccept: () => {enableMatomoAnalytics();},
          onReject: () => {disableMatomoAnalytics();}
        },
      }
    },
  },

  language: {
   default: "et",
    translations: {
      et: {
        consentModal: {
          title: 'Me kasutame küpsiseid',
          description: 'Tagamaks lehe mugavama ja isikupärasema kasutamise, kasutab käesolev veebileht küpsiseid.',
          acceptAllBtn: 'Nõustu kõigega',
          acceptNecessaryBtn: 'Keela kõik ära',
          showPreferencesBtn: 'Halda küpsiseid',
          // closeIconLabel: 'Reject all and close modal',
          footer: `
                        <a href="https://www.hm.ee/kupsised" target="_blank">Küpsistepoliitika</a>
                    `,
        },
        preferencesModal: {
          title: 'Manage cookie preferences',
          acceptAllBtn: 'Nõustu kõigega',
          acceptNecessaryBtn: 'Keela kõik ära',
          savePreferencesBtn: 'Nõustu praeguste valikutega',
          closeIconLabel: 'Close modal',
          serviceCounterLabel: 'Service|Services',
          sections: [
            {
              title: 'Sinu küpsise sätted',
              description: `1. hrl. väiksem ning kõvem küpsetatud kondiitritoode. Magusad, soolased küpsised. Pakk küpsiseid. Kohvi juurde pakuti küpsiseid.<br>
                            ▷ Liitsõnad: kaera(helbe)|küpsis, mandli|küpsis, muretai(g)na|küpsis, tee|küpsis, vanilliküpsis. <br>
                            2. van küpsetatud roog, hrl. praad. *Põhjuseks oli vasika tapmine, keda puht rõõsapiimaga suureks ja vanaks joodetud, et jätkuks temast süldiks ja ka küpsiseks. A. H. Tammsaare. <br>
                            3. info veebiserveri loodud ja kasutaja mäluseadmes talletatav kirje, mille poole veebiserver pöördub edasise suhtluse hõlbustamiseks.`,
            },
            {
              title: 'Hädavajalikud küpsised',
              description: 'These cookies are essential for the proper functioning of the website and cannot be disabled.',

              //this field will generate a toggle linked to the 'necessary' category
              linkedCategory: 'necessary'
            },
            {
              title: 'Statistika küpsised',
              description: 'These cookies collect information about how you use our website. All of the data is anonymized and cannot be used to identify you.',
              linkedCategory: 'analytics',
              toggle: {
                value: 'analytics',
                enabled: false,
                readonly: false
              },
            },
          ]
        }
      }
    }
  }
});


/* Helper functions to avoid repeating the same ga code */
function enableAnalytics(){
 // console.log("enabled analytics")
  if (typeof gtag != 'undefined') {
    gtag('consent', 'update', {
      'analytics_storage': 'granted',
    });
  }
}
function disableAnalytics(){
 // console.log("disabled analytics")
  if (typeof gtag != 'undefined') {
    gtag('consent', 'update', {
      'analytics_storage': 'denied'
    });
  }
}
function enableMatomoAnalytics() {
 // console.log("enabled Matomo analytics")
  _paq.push(['setConsentGiven']);
}
function disableMatomoAnalytics() {
  //console.log("disabled Matomo analytics")
  _paq.push(['forgetConsentGiven']);
}
