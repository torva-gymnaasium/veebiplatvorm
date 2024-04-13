<?php

namespace Drupal\harno_settings\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;

/**
 * Class HarnoSettingsForm.
 */
class HarnoSettingsForm extends ConfigFormBase {

  /**
   * HarnoSettingsForm constructor.
   *
   * @param ConfigFactoryInterface     $config_factory
   */
  public function __construct (
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($config_factory);
  }
  /**
   * @param ContainerInterface $container
   *
   * @return ConfigFormBase|HarnoSettingsForm
   */
  public static function create (ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'system.site',
      'harno_settings.settings',
    ];
  }
  public function getFormId() {
    return 'harno_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('harno_settings.settings');
    $config_site = $this->config('system.site');

    $form['tabs'] = [
      '#type' => 'vertical_tabs',
    ];

    ##################################################### Haridusasutuse üldinfo #################################################
    $form['general'] = [
      '#type' => 'details',
      '#title' => 'Haridusasutuse üldinfo',
      '#description' => 'Üldkontaktide sisestamise ja muutmise andmeplokk.',
      '#group' => 'tabs',
    ];

    $form['general']['site_name'] = [
      '#type' => 'textfield',
      '#title' => 'Haridusasutuse nimi',
      '#required' => TRUE,
      '#description' => 'Kuvatakse päises, jaluses ja veebilehitseja vahekaardil',
      '#default_value' =>  $config_site->get('name'),
    ];
    $form['general']['slogan'] = [
      '#type' => 'textfield',
      '#title' => 'Haridusasutuse moto',
      '#description' => 'Kuvatakse päises',
      '#default_value' =>  $config_site->get('slogan'),
    ];
    $form['general']['logo_default'] = [
      '#type' => 'value',
      '#value' => $config->get('general.logo'),
    ];
    $form['general']['logo_upload'] = [
      '#type' => 'managed_file',
      '#title' => 'Haridusasutuse logo',
      '#description' => 'Kuvatakse päises. Lubatud vormingud on .jpg, .jpeg, .png või .svg. Maksimaalne lubatud laius on 416px ja maksimaalne kõrgus on 224px.',
      '#default_value' =>  [$config->get('general.logo')],
      '#upload_location' => 'public://logo',
      '#upload_validators' => [
        'file_validate_image_resolution' => ['416x224', '0'],
        'file_validate_extensions' => [
          'jpg jpeg png svg'
        ],
      ],
    ];
    $form['general']['favicon_default'] = [
      '#type' => 'value',
      '#value' => $config->get('general.favicon'),
    ];
    $form['general']['favicon_upload'] = [
      '#type' => 'managed_file',
      '#title' => 'Haridusasutuse veebilehe tunnusikoon (favicon)',
      '#description' => 'Kuvatakse veebilehitseja vahekaardil. Lubatud vorming on .ico',
      '#default_value' =>  [$config->get('general.favicon')],
      '#upload_location' => 'public://',
      '#upload_validators' => [
        'file_validate_extensions' => [
          'ico',
        ],
      ],
    ];
    $form['general']['address'] = [
      '#type' => 'textfield',
      '#title' => 'Haridusasutuse üldkontakti aadress',
      '#description' => 'Kuvatakse jaluses',
      '#default_value' =>  $config->get('general.address'),
    ];
    $form['general']['phone'] = [
      '#type' => 'textfield',
      '#title' => 'Haridusasutuse üldkontakti telefoni number',
      '#description' => 'Kuvatakse jaluses',
      '#default_value' =>  $config->get('general.phone'),
    ];
    $form['general']['email'] = [
      '#type' => 'email',
      '#title' => 'Haridusasutuse üldkontakti e-posti aadress',
      '#description' => 'Kuvatakse jaluses',
      '#default_value' =>  $config->get('general.email'),
    ];
    $form['general']['domain'] = [
      '#type' => 'container',
      '#tree' => TRUE,

    ];
    for ($i = 1; $i<=2; $i++) {
      $form['general']['domain'][$i] = [
        '#type' => 'textfield',
        '#title' => 'Eelnev domeeninimi '. $i,
        '#description' => 'Eelnev domeeninimi<br/> Selle abil vahetatakse tekstides sisestatud linkide domeen ära praeguse vastu. <br/> Kui domeen ei ole vahetunud, siis seda pole vaja määrata<br/> Domeeninimi sisestada ilma www-ta',
        '#default_value' => \Drupal::state()->get('domain_'.$i),
      ];
    }
    $form['general']['prior_domain'][1] = [];
    ##################################################### Avalehe kiirlingid #################################################
    $form['frontpage_quick_links'] = [
      '#type' => 'details',
      '#title' => 'Avalehe kiirlingid',
      '#description' => 'Avalehe kiirlinkide sisestamise ja muutmise andmeplokk, kuhu saab lisada kuni 8 veebilehe sisemist või
       veebilehelt välja suunavat linki, mis märgistatakse vastava ikooniga. Lingi lisamiseks tuleb sisestada nii selle väljakuvatav nimi kui ka link.
       Lingi nimetus peaks olema võimalikult lühike ja konkreetne, võimalusel ainult 1 sõna. Ei soovita üle 2 sõna.
       Kui on soov lisada veebilehe sisemist linki, siis alustage soovitud lehekülje pealkirja trükkimist
       "Sisemine link" väljale ning süsteem pakub sobivaid linke. Lingi valimiseks klõpsake sellel. Välise lingi puhul kopeerige
       kogu veebilehe aadress algusega https:// või http:// ja lisage see "Väline veebilink" väljale. Linkide järjekorra muutmiseks minge rea alguses olevale ikoonile ja
       lohistage rida soovitud kohta. Muudatuste salvestamiseks tuleb vajutada "Salvesta seadistus" nuppu.',
      '#group' => 'tabs',
    ];
    $form['frontpage_quick_links']['fp_quick_links_table'] = [
      '#type' => 'table',
      '#header' => [
        'Veebilingi väljakuvatav nimi',
        'Sisemine link',
        'Väline veebilink',
        'Kaal'
      ],
      // TableDrag: Each array value is a list of callback arguments for
      // drupal_add_tabledrag(). The #id of the table is automatically
      // prepended; if there is none, an HTML ID is auto-generated.
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];
    for ($i = 0; $i < 8; $i++) {
      $j = $i + 1;
      // TableDrag: Mark the table row as draggable.
      $form['frontpage_quick_links']['fp_quick_links_table'][$i]['#attributes']['class'][] = 'draggable';
      // TableDrag: Sort the table row according to its existing/configured
      // weight.
      $form['frontpage_quick_links']['fp_quick_links_table'][$i]['#weight'] = $config->get('frontpage_quick_links.link_weight_' . $j);

      $form['frontpage_quick_links']['fp_quick_links_table'][$i]['link_name'] = [
        '#type' => 'textfield',
        '#title' => 'Veebilingi väljakuvatav nimi ' . $j,
        '#title_display' => 'invisible',
        '#size' => 35,
        '#default_value' => $config->get('frontpage_quick_links.link_name_' . $j),
      ];
      $form['frontpage_quick_links']['fp_quick_links_table'][$i]['link_entity'] = [
        '#type' => 'entity_autocomplete',
        '#size' => 20,
        '#title' => 'Sisemine link ' . $j,
        '#title_display' => 'invisible',
        '#target_type' => 'node',
        '#selection_handler' => 'default', // Optional. The default selection handler is pre-populated to 'default'.
        #'#selection_settings' => [
        #  'target_bundles' => ['article', 'page'],
        #],
      ];
      if (!empty($config->get('frontpage_quick_links.link_entity_' . $j))) {
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($config->get('frontpage_quick_links.link_entity_' . $j));
        $form['frontpage_quick_links']['fp_quick_links_table'][$i]['link_entity']['#default_value'] = $node;
      }
      $form['frontpage_quick_links']['fp_quick_links_table'][$i]['link_url'] = [
        '#type' => 'url',
        '#title' => 'Väline veebilink ' . $j,
        '#title_display' => 'invisible',
        '#size' => 30,
        #'#autocomplete_route_name' => 'linkit.autocomplete',
        #'#autocomplete_route_parameters' => [
        #  'linkit_profile_id' => 'default',
        #],
        '#default_value' => $config->get('frontpage_quick_links.link_url_' . $j),
      ];
      // TableDrag: Weight column element.
      $form['frontpage_quick_links']['fp_quick_links_table'][$i]['link_weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => 'link ' . $j]),
        '#title_display' => 'invisible',
        '#default_value' => $config->get('frontpage_quick_links.link_weight_' . $j),
        // Classify the weight element for #tabledrag.
        '#attributes' => ['class' => ['table-sort-weight']],
      ];
    }
    ##################################################### Avalehe teavitused  ######################################################
    $form['frontpage_messages'] = [
      '#type' => 'details',
      '#title' => 'Avalehe teavitused',
      '#description' => 'Avalehe teavitused on mõeldud olulise info lühikeseks esitamiseks. Muudatuste salvestamiseks tuleb
      vajutada "Salvesta seadistus" nuppu.',
      '#group' => 'tabs',
    ];

    $form['frontpage_messages']['details_1'] = [
      '#type' => 'details',
      '#title' => 'Esimene teavitus',
      '#open' => TRUE,
      '#group' => 'frontpage_messages',
    ];
    $form['frontpage_messages']['details_1']['frontpage_message_body_1'] = [
      '#title' => 'Teavituse sisu',
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#description' => 'Maksimaalne tähemärkide arv: 350',
      '#default_value' => $config->get('frontpage_messages.body_1'),
    ];
    $form['frontpage_messages']['details_1']['frontpage_message_type_1'] = [
      '#type' => 'select',
      '#title' => 'Teavituse värv',
      '#options' => [
        '1' => 'Kollane',
        '2' => 'Hall',
      ],
      '#default_value' => $config->get('frontpage_messages.type_1') ? $config->get('frontpage_messages.type_1') : 1,
    ];
    $form['frontpage_messages']['details_1']['frontpage_message_published_1'] = [
      '#type' => 'select',
      '#title' => 'Teavitus kuvatud',
      '#options' => [
        1 => $this->t('Yes'),
        0 => $this->t('No'),
      ],
      '#default_value' =>  $config->get('frontpage_messages.published_1') ? 1 : 0,
    ];
    $form['frontpage_messages']['details_2'] = [
      '#type' => 'details',
      '#title' => 'Teine teavitus',
      '#open' => TRUE,
    ];
    $form['frontpage_messages']['details_2']['frontpage_message_body_2'] = [
      '#title' => 'Teavituse sisu',
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#description' => 'Maksimaalne tähemärkide arv: 350',
      '#default_value' => $config->get('frontpage_messages.body_2'),
    ];
    $form['frontpage_messages']['details_2']['frontpage_message_type_2'] = [
      '#type' => 'select',
      '#title' => 'Teavituse värv',
      '#options' => [
        '1' => 'Kollane',
        '2' => 'Hall',
      ],
      '#default_value' => $config->get('frontpage_messages.type_2') ? $config->get('frontpage_messages.type_2') : 1,
    ];
    $form['frontpage_messages']['details_2']['frontpage_message_published_2'] = [
      '#type' => 'select',
      '#title' => 'Teavitus kuvatud',
      '#options' => [
        1 => $this->t('Yes'),
        0 => $this->t('No'),
      ],
      '#default_value' =>  $config->get('frontpage_messages.published_2') ? 1 : 0,
    ];

    ##################################################### Olulisemad kontaktid #################################################
    $form['important_contacts'] = [
      '#type' => 'details',
      '#title' => 'Jaluse olulisemad kontaktid',
      '#description' => 'Jaluse olulisemate kontaktide sisestamise ja muutmise andmeplokk,
      kuhu saab lisada kuni 4 kontakti. Kontaktile määratakse nimi ja sisu, kuid kummagi
      sisestamine ei ole kohustuslik. Linkide järjekorra muutmiseks minge rea alguses
      olevale ikoonile ja lohistage rida soovitud kohta. Muudatuste salvestamiseks tuleb
      vajutada "Salvesta seadistus" nuppu.',
      '#group' => 'tabs',
    ];

    $form['important_contacts']['contacts_table'] = [
      '#type' => 'table',
      '#header' => [
        'Kontakti nimi',
        'Kontakti sisu',
        'Kaal'
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];
    for ($i = 0; $i < 4; $i++) {
      $j = $i + 1;

      $form['important_contacts']['contacts_table'][$i]['#attributes']['class'][] = 'draggable';
      $form['important_contacts']['contacts_table'][$i]['#weight'] = $config->get('important_contacts.weight_' . $j);

      $form['important_contacts']['contacts_table'][$i]['name'] = [
        '#type' => 'textfield',
        '#title' => 'Kontakti nimi '. $j,
        '#title_display' => 'invisible',
        '#size' => 35,
        '#default_value' => $config->get('important_contacts.name_' . $j),
      ];
      $form['important_contacts']['contacts_table'][$i]['body'] = [
        '#type' => 'textfield',
        '#title' => 'Kontakti sisu '. $j,
        '#title_display' => 'invisible',
        '#size' => 35,
        '#default_value' => $config->get('important_contacts.body_' . $j),
      ];
      // TableDrag: Weight column element.
      $form['important_contacts']['contacts_table'][$i]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => 'Link ' . $j]),
        '#title_display' => 'invisible',
        '#default_value' => $config->get('important_contacts.weight_' . $j),
        // Classify the weight element for #tabledrag.
        '#attributes' => ['class' => ['table-sort-weight']],
      ];
    }
    ##################################################### Sotsiaalmeedia lingid #################################################
    $form['footer_socialmedia_links'] = [
      '#type' => 'details',
      '#title' => 'Jaluse sotsiaalmeedia lingid',
      '#description' => 'Jaluse sotsiaalmeedia linkide sisestamise ja muutmise andmeplokk, kuhu saab lisada kuni 9
       sotsiaalmeedia konto veebilink. Lingi lisamiseks tuleb sisestada selle ikoon, väljakuvatav nimi ja veebilink.
       Välise lingi puhul kopeerige kogu veebilehe aadress algusega https:// või http://. Linkide järjekorra muutmiseks minge rea alguses olevale ikoonile ja
       lohistage rida soovitud kohta. Muudatuste salvestamiseks tuleb vajutada "Salvesta seadistus" nuppu.',
      '#group' => 'tabs',
    ];
    $form['footer_socialmedia_links']['socialmedia_table'] = [
      '#type' => 'table',
      '#header' => [
        'Sotsiaalmeedia lingi ikoon',
        'Sotsiaalmeedia lingi nimetus',
        'Sotsiaalmeedia konto veebilink',
        'Kaal'
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];
    for ($i = 0; $i < 9; $i++) {
      $j = $i + 1;
      $form['footer_socialmedia_links']['socialmedia_table'][$i]['#attributes']['class'][] = 'draggable';
      $form['footer_socialmedia_links']['socialmedia_table'][$i]['#weight'] = $config->get('footer_socialmedia_links.link_weight_' . $j);

      $form['footer_socialmedia_links']['socialmedia_table'][$i]['link_icon'] = [
        '#type' => 'select',
        '#title' => 'Sotsiaalmeedia lingi ikoon ' . $j,
        '#title_display' => 'invisible',
        '#options' => [
          'mdi-blogger' => 'Blogger',
          'mdi-facebook' => 'Facebook',
          'mdi-instagram' => 'Instagram',
          'mdi-linkedin' => 'LinkedIn',
          'mdi-twitter' => 'Twitter',
          'mdi-vimeo' => 'Vimeo',
          'mdi-vk' => 'VKontakte',
          'mdi-youtube' => 'Youtube',
          'mdi-widgets' => $this->t('Other'),
        ],
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $config->get('footer_socialmedia_links.link_icon_' . $j),
      ];
      $form['footer_socialmedia_links']['socialmedia_table'][$i]['link_name'] = [
        '#type' => 'textfield',
        '#title' => 'Sotsiaalmeedia lingi nimetus ' . $j,
        '#title_display' => 'invisible',
        '#size' => 35,
        '#default_value' => $config->get('footer_socialmedia_links.link_name_' . $j),
      ];
      $form['footer_socialmedia_links']['socialmedia_table'][$i]['link_url'] = [
        '#type' => 'url',
        '#title' => 'Sotsiaalmeedia konto veebilink ' . $j,
        '#title_display' => 'invisible',
        '#size' => 35,
        '#default_value' => $config->get('footer_socialmedia_links.link_url_' . $j),
      ];
      $form['footer_socialmedia_links']['socialmedia_table'][$i]['link_weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => 'link ' . $j]),
        '#title_display' => 'invisible',
        '#default_value' => $config->get('footer_socialmedia_links.link_weight_' . $j),
        '#attributes' => ['class' => ['table-sort-weight']],
      ];
    }
    ##################################################### Jaluse kiirlingid #################################################
    $form['footer_quick_links'] = [
      '#type' => 'details',
      '#title' => 'Jaluse kiirlingid',
      '#description' => 'Jaluse kiirlinkide sisestamise ja muutmise andmeplokk, kuhu saab lisada kuni 8 veebilehe sisemist või
       veebilehelt välja suunavat linki, mis märgistatakse vastava ikooniga. Lingi lisamiseks tuleb sisestada nii selle väljakuvatav nimi kui ka link.
       Kui on soov lisada veebilehe sisemist linki, siis alustage soovitud lehekülje pealkirja trükkimist
       "Sisemine link" väljale ning süsteem pakub sobivaid linke. Lingi valimiseks klõpsake sellel. Välise lingi puhul kopeerige
       kogu veebilehe aadress algusega https:// või http:// ja lisage see "Väline veebilink" väljale. Linkide järjekorra muutmiseks minge rea alguses olevale ikoonile ja
       lohistage rida soovitud kohta. Muudatuste salvestamiseks tuleb vajutada "Salvesta seadistus" nuppu.',
      '#group' => 'tabs',
    ];
    $form['footer_quick_links']['quick_links_table'] = [
      '#type' => 'table',
      '#header' => [
        'Veebilingi väljakuvatav nimi',
        'Sisemine link',
        'Väline veebilink',
        'Kaal'
      ],
      // TableDrag: Each array value is a list of callback arguments for
      // drupal_add_tabledrag(). The #id of the table is automatically
      // prepended; if there is none, an HTML ID is auto-generated.
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];
    for ($i = 0; $i < 8; $i++) {
      $j = $i + 1;
      // TableDrag: Mark the table row as draggable.
      $form['footer_quick_links']['quick_links_table'][$i]['#attributes']['class'][] = 'draggable';
      // TableDrag: Sort the table row according to its existing/configured
      // weight.
      $form['footer_quick_links']['quick_links_table'][$i]['#weight'] = $config->get('footer_quick_links.link_weight_' . $j);

      $form['footer_quick_links']['quick_links_table'][$i]['link_name'] = [
        '#type' => 'textfield',
        '#title' => 'Veebilingi väljakuvatav nimi ' . $j,
        '#title_display' => 'invisible',
        '#size' => 35,
        '#default_value' => $config->get('footer_quick_links.link_name_' . $j),
      ];
      $form['footer_quick_links']['quick_links_table'][$i]['link_entity'] = [
        '#type' => 'entity_autocomplete',
        '#size' => 20,
        '#title' => 'Sisemine link ' . $j,
        '#title_display' => 'invisible',
        '#target_type' => 'node',
        '#selection_handler' => 'default', // Optional. The default selection handler is pre-populated to 'default'.
        #'#selection_settings' => [
        #  'target_bundles' => ['article', 'page'],
        #],
      ];
      if (!empty($config->get('footer_quick_links.link_entity_' . $j))) {
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($config->get('footer_quick_links.link_entity_' . $j));
        $form['footer_quick_links']['quick_links_table'][$i]['link_entity']['#default_value'] = $node;
      }
      $form['footer_quick_links']['quick_links_table'][$i]['link_url'] = [
        '#type' => 'url',
        '#title' => 'Väline veebilink ' . $j,
        '#title_display' => 'invisible',
        '#size' => 30,
        #'#autocomplete_route_name' => 'linkit.autocomplete',
        #'#autocomplete_route_parameters' => [
        #  'linkit_profile_id' => 'default',
        #],
        '#default_value' => $config->get('footer_quick_links.link_url_' . $j),
      ];
      // TableDrag: Weight column element.
      $form['footer_quick_links']['quick_links_table'][$i]['link_weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => 'link ' . $j]),
        '#title_display' => 'invisible',
        '#default_value' => $config->get('footer_quick_links.link_weight_' . $j),
        // Classify the weight element for #tabledrag.
        '#attributes' => ['class' => ['table-sort-weight']],
      ];
    }

    ##################################################### Jaluse vabatekstiala #################################################
    $form['footer_free_text_area'] = [
      '#type' => 'details',
      '#title' => 'Jaluse vabatekstiala',
      '#description' => 'Jaluse vabateksti sisestamise ja muutmise andmeplokk, kuhu on võimalik lisada pealkiri ja sisu.
      Pealkiri on kohustuslik, kui vabateksti sisu on sisestatud. Sisu on kohustuslik, kui vabateksti pealkiri on sisestatud.',
      '#group' => 'tabs',
    ];
    $form['footer_free_text_area']['free_text_name'] = [
      '#type' => 'textfield',
      '#title' => 'Vabatekstiala pealkiri',
      '#default_value' =>  $config->get('footer_free_text_area.name'),
    ];
    $form['footer_free_text_area']['free_text_body'] = [
      '#type' => 'textarea',
      '#title' => 'Vabatekstiala sisu',
      '#default_value' =>  $config->get('footer_free_text_area.body'),
    ];
    ##################################################### Jaluse vabatekstiala #################################################
    $form['footer_copyright'] = [
      '#type' => 'details',
      '#title' => 'Jaluse kasutusõiguste märkus',
      '#description' => 'Jaluse kasutusõiguste märkuse sisestamise ja muutmise andmeplokk, kuhu on võimalik lisada lehe kasutusõiguste tekst.',
      '#group' => 'tabs',
    ];
    $form['footer_copyright']['footer_copyright_name'] = [
      '#type' => 'textfield',
      '#title' => 'Kasutusõiguste märkus',
      '#default_value' =>  $config->get('footer_copyright.name'),
    ];
    ##################################################### Suunamised ja muutujad #################################################
    $form['variables'] = [
      '#type' => 'details',
      '#title' => 'Suunamised ja muutujad',
      '#description' => 'Siin andmeplokis on muutujad, mida administraator saab lehte seadistades täpsustada. Näiteks saab anda uudise tüübile "Meie lugu" uue kuvatava nimetuse nt "Kooli blogi". Samuti saab siin plokis seadistada lehte, kuhu suunatakse ligipääsetavuse avalduse lingil vajutades.',
      '#group' => 'tabs',
    ];
    $form['variables']['news_our_story_name'] = [
      '#type' => 'textfield',
      '#title' => 'Uudise tüübi "Meie lugu" nimetus',
      '#default_value' =>  $config->get('news_our_story.name'),
      '#required' => TRUE,
    ];
    $form['variables']['automatic_generation_academic_year_on'] = [
      '#type' => 'select',
      '#title' => 'Õppeaasta automaatne genereerimine on sisselülitatud',
      '#options' => [
        1 => $this->t('Yes'),
        0 => $this->t('No'),
      ],
      '#default_value' =>  $config->get('automatic_generation_academic_year.on') ? 1 : 0,
      '#required' => TRUE,
    ];
    $form['variables']['automatic_generation_academic_year_date'] = [
      '#type' => 'textfield',
      '#title' => 'Õppeaasta automaatse genereerimise kuupäev',
      '#default_value' =>  $config->get('automatic_generation_academic_year.date'),
      '#size' => 5,
      '#description' => 'Kuupäeva formaat on "dd.mm.".',
      '#required' => TRUE,
    ];
    $form['variables']['accessibility_statement_node'] = [
      '#type' => 'entity_autocomplete',
      '#title' => 'Ligipääsetavuse avalduse leht',
      '#target_type' => 'node',
      '#selection_handler' => 'default', // Optional. The default selection handler is pre-populated to 'default'.
      '#selection_settings' => [
        'target_bundles' => ['page'],
      ],
      '#description' => 'Siia lisada viide ligipääsetavuse avalduse lehele. See tekitab lingi päisesse ligipääsetavuse aknasse. Alustage soovitud lehekülje pealkirja trükkimist ning süsteem pakub sobivaid linke. Lingi valimiseks klõpsake sellel.',
    ];
    if (!empty($config->get('accessibility_statement.node' ))) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($config->get('accessibility_statement.node'));
      $form['variables']['accessibility_statement_node']['#default_value'] = $node;
    }
    $form['variables']['location_node'] = [
      '#type' => 'entity_autocomplete',
      '#title' => 'Asukoha info leht',
      '#target_type' => 'node',
      '#selection_handler' => 'default', // Optional. The default selection handler is pre-populated to 'default'.
      '#selection_settings' => [
        'target_bundles' => ['page', 'location'],
      ],
      '#description' => 'Siia lisada viide asukoha info lehele. See tekitab lingi jalusesse üldkontaktide alla. Alustage soovitud lehekülje pealkirja trükkimist ning süsteem pakub sobivaid linke. Lingi valimiseks klõpsake sellel.',
    ];
    if (!empty($config->get('location.node' ))) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($config->get('location.node'));
      $form['variables']['location_node']['#default_value'] = $node;
    }

    $timestamp_last = \Drupal::state()->get('harno_settings.juhan_api_sync_last_run') ?? 0;
    if($timestamp_last > 0) {
      $desc = ' Viimati uuendatud: ' . date('d.m.Y H:i', $timestamp_last);
    } else {
      $desc = '';
    }
    $form['variables']['juhan_api_key'] = [
      '#type' => 'textfield',
      '#title' => 'Juhani API võti',
      '#default_value' =>  $config->get('juhan.api_key'),
      '#description' => 'API võtme saab asutuse haldur Juhani tootejuhi käest pärast koolituse läbimist. Vastavalt API võtmele saab koolitusi veebilehele importida.' . $desc,
      '#required' => FALSE,
    ];
    if ($config->get('juhan.api_key') AND $timestamp_last > 0) {
      $form['variables']['juhan_sync'] = [
        '#type' => 'submit',
        '#value' => 'Uuenda Juhani koolitusi',
        '#submit' => array([$this, 'submitJuhanSync']),
        '#limit_validation_errors' => array(),
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $frontpage_quick_links = $form_state->getValue('fp_quick_links_table');
    foreach ($frontpage_quick_links as $id => $item) {
      $j = $id + 1;
      if (!empty($item['link_name']) AND empty($item['link_entity']) AND empty($item['link_url'])) {
        $message = 'Palun täida avalehe kiirlingid real '.$j. ', kas väli "sisemine link" või "väline veebilink".';
        $form_state->setErrorByName('fp_quick_links_table][' . $id . '][link_entity', $message);
        $form_state->setErrorByName('fp_quick_links_table][' . $id . '][link_url', $message);
      }
      if (!empty($item['link_entity']) AND empty($item['link_name'])) {
        $form_state->setErrorByName('fp_quick_links_table]['.$id.'][link_name', $this->t('@name field is required.', ['@name' => '"veebilingi väljakuvatav nimi" real ' . $j]));
      }
      if (!empty($item['link_url']) AND empty($item['link_name'])) {
        $form_state->setErrorByName('fp_quick_links_table]['.$id.'][link_name', $this->t('@name field is required.', ['@name' => '"veebilingi väljakuvatav nimi" real ' . $j]));
      }
    }

    $socialmedia_links = $form_state->getValue('socialmedia_table');
    foreach ($socialmedia_links as $id => $item) {
      $j = $id + 1;
      if (!empty($item['link_icon'])) {
        if (empty($item['link_name'])) {
          $form_state->setErrorByName('socialmedia_table][' . $id . '][link_name', $this->t('@name field is required.', ['@name' => '"sotsiaalmeedia lingi nimetus" real ' . $j]));
        }
        if (empty($item['link_url'])) {
          $form_state->setErrorByName('socialmedia_table][' . $id . '][link_url', $this->t('@name field is required.', ['@name' => '"sotsiaalmeedia konto veebilink" real ' . $j]));
        }
      }

      if (!empty($item['link_name'])) {
        if (empty($item['link_icon'])) {
          $form_state->setErrorByName('socialmedia_table][' . $id . '][link_icon', $this->t('@name field is required.', ['@name' => '"sotsiaalmeedia lingi ikoon" real ' . $j]));
        }
        if (empty($item['link_url'])) {
          $form_state->setErrorByName('socialmedia_table][' . $id . '][link_url', $this->t('@name field is required.', ['@name' => '"sotsiaalmeedia konto veebilink" real ' . $j]));
        }
      }
      if (!empty($item['link_url'])) {
        if (empty($item['link_icon'])) {
          $form_state->setErrorByName('socialmedia_table][' . $id . '][link_icon', $this->t('@name field is required.', ['@name' => '"sotsiaalmeedia lingi ikoon" real ' . $j]));
        }
        if (empty($item['link_name'])) {
          $form_state->setErrorByName('socialmedia_table][' . $id . '][link_name', $this->t('@name field is required.', ['@name' => '"sotsiaalmeedia lingi nimetus" real ' . $j]));
        }
      }
    }

    $footer_quick_links = $form_state->getValue('quick_links_table');
    foreach ($footer_quick_links as $id => $item) {
      $j = $id + 1;
      if (!empty($item['link_name']) AND empty($item['link_entity']) AND empty($item['link_url'])) {
        $message = 'Palun täida jaluse kiirlingid real '.$j. ', kas väli "sisemine link" või "väline veebilink".';
        $form_state->setErrorByName('quick_links_table][' . $id . '][link_entity', $message);
        $form_state->setErrorByName('quick_links_table][' . $id . '][link_url', $message);
      }
      if (!empty($item['link_entity']) AND empty($item['link_name'])) {
        $form_state->setErrorByName('quick_links_table]['.$id.'][link_name', $this->t('@name field is required.', ['@name' => '"veebilingi väljakuvatav nimi" real ' . $j]));
      }
      if (!empty($item['link_url']) AND empty($item['link_name'])) {
        $form_state->setErrorByName('quick_links_table]['.$id.'][link_name', $this->t('@name field is required.', ['@name' => '"veebilingi väljakuvatav nimi" real ' . $j]));
      }
    }

    if (!empty($form_state->getValue('free_text_name')) AND empty($form_state->getValue('free_text_body'))) {
      $form_state->setErrorByName('free_text_body', $this->t('@name field is required.', ['@name' => '"vabatekstiala sisu"']));
    }
    if (!empty($form_state->getValue('free_text_body')) AND empty($form_state->getValue('free_text_name'))) {
      $form_state->setErrorByName('free_text_name', $this->t('@name field is required.', ['@name' => '"vabatekstiala pealkiri"']));
    }
    if (!empty($form_state->getValue('frontpage_message_body_1')['value'])) {
      $len = strlen(strip_tags($form_state->getValue('frontpage_message_body_1')['value']));
      if ($len > 350) {
        $form_state->setErrorByName('frontpage_message_body_1', 'Teavituse sisu maksimaalne tähemärkide arv on 350. Hetkel sisaldab see '.$len.' tähemärki.');
      }
    }
    if (!empty($form_state->getValue('frontpage_message_body_2')['value'])) {
      $len = strlen(strip_tags($form_state->getValue('frontpage_message_body_2')['value']));
      if ($len > 350) {
        $form_state->setErrorByName('frontpage_message_body_2', 'Teavituse sisu maksimaalne tähemärkide arv on 350. Hetkel sisaldab see '.$len.' tähemärki.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    ################################################### Domain save ####################################
    if ($domains = $form_state->getValue('domain')) {
      foreach ($domains as $key => $domain) {
        \Drupal::state()->set('domain_' . $key, $domain);
      }
    }

    ################################################### Logo save ######################################
    if ($form_state->getValue('logo_upload')) {
      $logo_upload = $form_state->getValue('logo_upload')[0];
    }
    else {
      $logo_upload = 0;
    }
    $this->fileUploadHandle($logo_upload, $form_state->getValue('logo_default'), 'general.logo');

    ################################################### Favicon save ######################################
    if ($form_state->getValue('favicon_upload')) {
      $favicon_upload = $form_state->getValue('favicon_upload')[0];
    }
    else {
      $favicon_upload = 0;
    }
    $this->fileUploadHandle($favicon_upload, $form_state->getValue('favicon_default'), 'general.favicon');

    ################################################### Other general settings save ######################################
    $this->config('harno_settings.settings')
      ->set('general.address', $form_state->getValue('address'))
      ->set('general.phone', $form_state->getValue('phone'))
      ->set('general.email', $form_state->getValue('email'))
      ->set('footer_free_text_area.name', $form_state->getValue('free_text_name'))
      ->set('footer_free_text_area.body', $form_state->getValue('free_text_body'))
      ->set('footer_copyright.name', $form_state->getValue('footer_copyright_name'))
      ->set('automatic_generation_academic_year.on', $form_state->getValue('automatic_generation_academic_year_on'))
      ->set('automatic_generation_academic_year.date', $form_state->getValue('automatic_generation_academic_year_date'))
      ->set('news_our_story.name', $form_state->getValue('news_our_story_name'))
      ->set('juhan.api_key', $form_state->getValue('juhan_api_key'))
      ->set('accessibility_statement.node', $form_state->getValue('accessibility_statement_node'))
      ->set('location.node', $form_state->getValue('location_node'))
      ->save();
    ################################################### Frontpage quick links settings save ######################################

    $frontpage_quick_links = $form_state->getValue('fp_quick_links_table');

    $keys = array_column($frontpage_quick_links, 'link_weight');
    array_multisort($keys, SORT_ASC, $frontpage_quick_links);

    foreach ($frontpage_quick_links as $id => $item) {
      $j = $id + 1;

      $this->config('harno_settings.settings')
        ->set('frontpage_quick_links.link_name_'. $j, $item['link_name'])
        ->set('frontpage_quick_links.link_entity_'. $j, $item['link_entity'])
        ->set('frontpage_quick_links.link_url_'. $j, $item['link_url'])
        ->set('frontpage_quick_links.lin k_weight_'. $j, $item['link_weight'])
        ->save();
    }
    ################################################### Frontpage messages settings save ######################################
      $this->config('harno_settings.settings')
        ->set('frontpage_messages.body_1', $form_state->getValue('frontpage_message_body_1')['value'])
        ->set('frontpage_messages.type_1', $form_state->getValue('frontpage_message_type_1'))
        ->set('frontpage_messages.published_1', $form_state->getValue('frontpage_message_published_1'))
        ->set('frontpage_messages.body_2', $form_state->getValue('frontpage_message_body_2')['value'])
        ->set('frontpage_messages.type_2', $form_state->getValue('frontpage_message_type_2'))
        ->set('frontpage_messages.published_2', $form_state->getValue('frontpage_message_published_2'))
        ->save();

    ################################################### System site settings save ######################################

    $this->config('system.site')
      ->set('name', $form_state->getValue('site_name'))
      ->set('slogan', $form_state->getValue('slogan'))
      ->save();

    ################################################### Important contacts settings save ######################################
    $footer_important_contacts = $form_state->getValue('contacts_table');

    $keys = array_column($footer_important_contacts, 'weight');
    array_multisort($keys, SORT_ASC, $footer_important_contacts);

    foreach ($footer_important_contacts as $id => $item) {
      $j = $id + 1;
      $this->config('harno_settings.settings')
        ->set('important_contacts.name_'. $j, $item['name'])
        ->set('important_contacts.body_'. $j, $item['body'])
        ->set('important_contacts.weight_'. $j, $item['weight'])
        ->save();
    }
    ################################################### Footer socialmedia settings save ######################################

    $socialmedia_links = $form_state->getValue('socialmedia_table');

    $keys = array_column($socialmedia_links, 'link_weight');
    array_multisort($keys, SORT_ASC, $socialmedia_links);

    foreach ($socialmedia_links as $id => $item) {
      $j = $id + 1;
      $this->config('harno_settings.settings')
        ->set('footer_socialmedia_links.link_icon_'. $j, $item['link_icon'])
        ->set('footer_socialmedia_links.link_name_'. $j, $item['link_name'])
        ->set('footer_socialmedia_links.link_url_'. $j, $item['link_url'])
        ->set('footer_socialmedia_links.link_weight_'. $j, $item['link_weight'])
        ->save();
    }
    ################################################### Footer quick links settings save ######################################

    $footer_quick_links = $form_state->getValue('quick_links_table');

    $keys = array_column($footer_quick_links, 'link_weight');
    array_multisort($keys, SORT_ASC, $footer_quick_links);

    foreach ($footer_quick_links as $id => $item) {
      $j = $id + 1;

      $this->config('harno_settings.settings')
        ->set('footer_quick_links.link_name_'. $j, $item['link_name'])
        ->set('footer_quick_links.link_entity_'. $j, $item['link_entity'])
        ->set('footer_quick_links.link_url_'. $j, $item['link_url'])
        ->set('footer_quick_links.link_weight_'. $j, $item['link_weight'])
        ->save();
    }
    Cache::invalidateTags(['harno-config']);
  }

  /**
  * {@inheritdoc}
  */
  public function submitJuhanSync(array &$form, FormStateInterface $form_state)
  {
    \Drupal::service('harno_settings.juhan_api_sync')->syncTrainings(TRUE);
  }

  /**
   * fileUploadHandle.
   *
   * @param \Drupal\file\Entity\File  $upload_fid
   * @param \Drupal\file\Entity\File  $default_fid
   * @param  harno_settings_schema    $config_var_name
   */
  private function fileUploadHandle ($upload_fid, $default_fid, $config_var_name) {
    #Remove file usage and mark it temporary, if new file uploaded.
    if ((!empty($default_fid) AND !$upload_fid) OR $default_fid != $upload_fid) {
      $file = File::load($default_fid);
      // Set the status flag temporary of the file object.
      if (!empty($file) AND $file->isPermanent()) {
        $file_usage = \Drupal::service('file.usage');
        $file_usage->delete($file, 'harno_settings', 'node', 1);
        $file->setTemporary();
        $file->save();
      }
      $this->config('harno_settings.settings')
        ->set($config_var_name, 0);
    }

    if ($upload_fid) {
      // Load the object of the file by its fid.
      $file = File::load($upload_fid);
      // Set the status flag permanent of the file object.
      if (!empty($file)) {
        if ($file->isTemporary()) {
          $file->setPermanent();
          // Save the file in the database.
          $file->save();
          $file_usage = \Drupal::service('file.usage');
          $file_usage->add($file, 'harno_settings', 'node', 1);
        }
        $this->config('harno_settings.settings')
          ->set($config_var_name, $upload_fid);
      }
    }
  }
}
