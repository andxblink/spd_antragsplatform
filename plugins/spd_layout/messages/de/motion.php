<?php

/**
 * Frontend wording for the SPD Antragsplatform.
 *
 * Only the strings that named the upstream product. Merged over messages/de/
 * by MessageSource, so every other string still comes from upstream.
 */

return [
    'submitted_screening_email' => "Hallo,\n\ndu hast soeben einen Antrag eingereicht.\n" .
                                            "Der Antrag wird nun auf formale Richtigkeit geprüft und dann freigeschaltet. " .
                                            "Du wirst dann gesondert darüber benachrichtigt.\n\n" .
                                            "Du kannst ihn hier einsehen: %LINK%\n\n" .
                                            "Mit freundlichen Grüßen,\n" .
                                            "  Das Antragsplatform-Team",
    'published_email_body' => "Hallo,\n\ndein Antrag „%TITLE%“ wurde soeben auf der Antragsplatform veröffentlicht. " .
                                            "Du kannst ihn hier einsehen: %LINK%\n\n" .
                                            "Mit freundlichen Grüßen,\n" .
                                            "  Das Antragsplatform-Team",
    'support_collect_explanation' => 'Ein Antrag, der nicht von Parteigremien gestellt wird, kann gemäß unserer Statuten auch gemeinschaftlich von %MIN+1% Mitgliedern gestellt werden. Um einen solchen Antrag zu stellen und offiziell bei der Antragskommission einzureichen, gehe bitte wie folgt vor:<br>
<ol>
<li><strong>Antrag anlegen:</strong> Bitte lege auf dieser Seite deinen Antrag an und hinterlege deine (Kontakt-)Daten. Kontrolliere auf der nächsten Seite, ob alles richtig ist, und bestätige, dass du den Antrag wie gezeigt anlegen möchtest.</li>
<li><strong>Unterstützung finden:</strong> Nach der Bestätigung erhältst du einen Link, den du Interessierten weiterleiten kannst. Über diesen Link kann dein Antrag gelesen und von Parteimitgliedern unterstützt werden, die damit mit Dir gemeinsam den Antrag stellen. Bitte beachte, dass sich auch die Unterstützer*innen deines Antrags mit ihren Zugangsdaten anmelden und sich darüber verifizieren müssen.</li>
<li><strong>Antrag einreichen:</strong> Sobald sich die notwendigen %MIN% Unterstützer*innen gefunden haben, musst du den angelegten Antrag noch offiziell bei der Antragskommission einreichen. Dies veranlasst Du auch über die Antragsplatform. Wir informieren dich selbstverständlich per E-Mail, sobald die notwendige Zahl der Unterstützer*innen erreicht ist.</li>
</ol>',
    'support_reached_email_body' => 'Hallo,<br><br>Gute Nachrichten! Dein Antrag „%TITLE%“ hat die Mindestzahl an Unterstützer*innen erreicht.<br><br>Jetzt bist du noch einmal am Zug. Bitte reiche deinen Antrag nun offiziell bei der Antragskommission ein. Dies kannst du über die Antragsplatform unter dem folgenden Link machen:<br><br><strong>%LINK%</strong><br><br>Der Antrag wird zeitnah nach einer formalen Prüfung im Antragsplatform veröffentlicht.<br><br>Mit freundlichen Grüßen,<br>  Das Antragsplatform-Team',
    'proposal_email_modified' => "Hallo %NAME_GIVEN%,\n\n" .
                                 "die Programmkommission hat über deinen Antrag beraten und will vorschlagen, ihn modifiziert zu übernehmen.\n\n" .
                                 "Bitte überprüfe unter folgendem Link, ob du mit der modifizierten Übernahme so einverstanden bist:\n%LINK%\n\n" .
                                 "Mit freundlichen Grüßen,\n" .
                                 "  Das Antragsplatform-Team",
    'proposal_email_accepted' => "Hallo %NAME_GIVEN%,\n\n" .
                                 "zu Deiner Information: die Programmkommission wird vorschlagen, deinen Antrag zu übernehmen.\n\n" .
                                 "Du kannst ihn hier einsehen: %LINK%\n\n" .
                                 "Mit freundlichen Grüßen,\n" .
                                 "  Das Antragsplatform-Team",
    'proposal_email_other' => "Hallo %NAME_GIVEN%,\n\n" .
                                 "die Programmkommission hat einen Verfahrensvorschlag für deinen Antrag erarbeitet.\n\n" .
                                 "Bitte überprüfe unter folgendem Link, ob du damit einverstanden bist:\n%LINK%\n\n" .
                                 "Hinweis: wenn du den eben genannten Link weiterleitest, kann die empfangende Person den Vorschlag damit ebenfalls einsehen und ggf. bestätigen.\n\n" .
                                 "Mit freundlichen Grüßen,\n" .
                                 "  Das Antragsplatform-Team",
];
