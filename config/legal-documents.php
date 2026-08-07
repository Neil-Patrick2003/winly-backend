<?php

/*
|--------------------------------------------------------------------------
| Legal Document Content
|--------------------------------------------------------------------------
|
| The wording of the Terms and the Privacy Policy, held as structure rather
| than as prose in a template.
|
| It lives here because two things render it and they must not disagree: the
| public web pages, which are what App Store Connect and any crawler see, and
| the API the mobile app reads to draw the same documents natively. Wording
| kept in a Blade file and repeated in a React screen drifts within a release
| or two, and a policy that says something different depending on where you
| read it is worse than having none.
|
| Placeholders written as :name are filled from config/legal.php by
| App\Actions\ResolveLegalDocument. Change details there, not here.
|
| TEMPLATE — NOT LEGAL ADVICE. Have a lawyer read both documents before you
| rely on them, and keep them true to what the code actually does.
|
| Block types: 'p' (paragraph), 'ul' (bulleted list), 'callout' (an emphasised
| group of paragraphs).
|
*/

return [

    'terms' => [
        'key' => 'terms',
        'title' => 'Terms of Service',
        'sections' => [
            [
                'heading' => '1. Who we are, and what this is',
                'blocks' => [
                    ['type' => 'p', 'text' => ':app is an app for logging small daily wins — movement, learning and meditation — and sharing them with people you choose. These terms are the agreement between you and :company covering your use of it.'],
                    ['type' => 'p', 'text' => 'By creating an account you accept these terms. If you do not accept them, do not create an account.'],
                ],
            ],
            [
                'heading' => '2. Who can use it',
                'blocks' => [
                    ['type' => 'p', 'text' => 'You must be at least :age years old to hold an account. If you are under the age of majority where you live, you may only use :app with a parent or guardian’s permission.'],
                    ['type' => 'p', 'text' => 'One person, one account. You are responsible for what happens under yours, so keep your password to yourself and tell us if you think someone else has it.'],
                ],
            ],
            [
                'heading' => '3. Your content stays yours',
                'blocks' => [
                    ['type' => 'p', 'text' => 'The wins you log, the photos and clips you attach, your comments and your stories remain yours. You are not signing them over to us.'],
                    ['type' => 'p', 'text' => 'You do give us permission to store your content and to show it to the people you have chosen to share it with — that permission is simply what makes the app able to work. It ends when you delete the content or your account, except for copies sitting in routine backups, which age out.'],
                ],
            ],
            [
                'heading' => '4. Content that is not allowed',
                'blocks' => [
                    ['type' => 'callout', 'text' => [
                        'There is no tolerance for objectionable content or abusive behaviour on :app.',
                        'Content that is unlawful, harassing, hateful, threatening, defamatory, sexually explicit, violent, or that promotes self-harm or discrimination, is not permitted. Neither is impersonating somebody else, or targeting, bullying or abusing another person.',
                        'You can report content and block other people from inside the app. We review every report and act on it within 24 hours — removing the content, and removing the account behind it where that is warranted. Accounts responsible for such content are ejected.',
                    ]],
                    ['type' => 'p', 'text' => 'You also agree not to:'],
                    ['type' => 'ul', 'items' => [
                        'post anything you do not have the right to post;',
                        'collect other people’s information from the app, by scraping or otherwise;',
                        'interfere with the service, or try to reach parts of it you have not been given access to;',
                        'use it to send spam or advertising; or',
                        'use automated means to create accounts or post content.',
                    ]],
                ],
            ],
            [
                'heading' => '5. Ending it',
                'blocks' => [
                    ['type' => 'p', 'text' => 'You can delete your account at any time from inside the app. Deleting it removes your profile, your wins and your content, on the timeline set out in the Privacy Policy.'],
                    ['type' => 'p', 'text' => 'We may suspend or remove an account that breaks these terms. Where it is reasonable to do so, we will tell you why; where the content is seriously harmful, we may act first.'],
                ],
            ],
            [
                'heading' => '6. The app is provided as it is',
                'blocks' => [
                    ['type' => 'p', 'text' => 'We work to keep :app running and to keep your content safe, but we cannot promise it will always be available, uninterrupted, or free of faults. To the fullest extent the law allows, it is provided “as is”, without warranties of any kind.'],
                    ['type' => 'p', 'text' => ':app is for personal wellbeing and motivation. It is not medical, psychological or professional advice, and it is not a substitute for any of them.'],
                ],
            ],
            [
                'heading' => '7. Limits on what we owe you',
                'blocks' => [
                    ['type' => 'p', 'text' => 'To the fullest extent the law allows, :company is not liable for indirect or consequential losses arising from your use of the app, and our total liability is limited to the amount you have paid us in the twelve months before the claim.'],
                    ['type' => 'p', 'text' => 'Nothing here removes rights you have under consumer law that cannot be removed by agreement.'],
                ],
            ],
            [
                'heading' => '8. Changes',
                'blocks' => [
                    ['type' => 'p', 'text' => 'We may update these terms. If a change matters, we will tell you in the app or by email before it takes effect. Carrying on using :app after that means you accept the new version. The date at the top tells you which version you are reading.'],
                ],
            ],
            [
                'heading' => '9. Law',
                'blocks' => [
                    ['type' => 'p', 'text' => 'These terms are governed by the laws of :jurisdiction, and its courts have jurisdiction over any dispute — without affecting any right you have to bring a claim where you live.'],
                ],
            ],
            [
                'heading' => '10. Reaching us',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Questions, complaints, or reports of abuse: :email.'],
                ],
            ],
        ],
    ],

    'privacy' => [
        'key' => 'privacy',
        'title' => 'Privacy Policy',
        'sections' => [
            [
                'heading' => 'The short version',
                'blocks' => [
                    ['type' => 'p', 'text' => 'We collect what the app needs to work, and nothing to sell. We do not sell your personal information, and we do not share it with advertisers. You can delete your account, and your content, from inside the app.'],
                ],
            ],
            [
                'heading' => 'What we collect',
                'blocks' => [
                    ['type' => 'p', 'text' => 'What you give us when you sign up: your name, username, email address and password. The password is stored only as a cryptographic hash — we never see it, and cannot recover it for you.'],
                    ['type' => 'p', 'text' => 'What you add to your profile: a bio, an avatar, a cover image, and whether your account is private.'],
                    ['type' => 'p', 'text' => 'What you log and post: your wins across movement, learning and meditation, along with any photos or clips you attach; your comments, stories, and reactions; the circles you belong to and who you follow.'],
                    ['type' => 'p', 'text' => 'What the app needs to run: a token for your signed-in device, and a push notification token if you allow notifications. We keep a record of when you were last active, so streaks work.'],
                    ['type' => 'p', 'text' => 'Ordinary server logs: IP address, the requests made, and timestamps — kept briefly, for security and for finding faults.'],
                ],
            ],
            [
                'heading' => 'What we do with it',
                'blocks' => [
                    ['type' => 'ul', 'items' => [
                        'Run the app: show your feed, keep your streak, deliver your wins to your circles.',
                        'Send you notifications you have asked for, and emails you need — a password reset code, for instance.',
                        'Keep accounts safe: spotting abuse, enforcing the Terms of Service, and acting on reports.',
                        'Understand how the app is used in aggregate, so it can be improved.',
                    ]],
                    ['type' => 'p', 'text' => 'We do not use your content to advertise to you, and we do not sell it.'],
                ],
            ],
            [
                'heading' => 'Who else sees it',
                'blocks' => [
                    ['type' => 'p', 'text' => 'The people you choose. Wins shared into a circle are visible to that circle’s members. A private account is visible only to accounts you approve. This is the main way your content reaches anybody.'],
                    ['type' => 'p', 'text' => 'Services that help us operate, and only for that purpose:'],
                    ['type' => 'ul', 'items' => [
                        'hosting and databases, which hold your account and content;',
                        'file storage, which holds your photos and clips;',
                        'an email provider, which delivers messages such as reset codes;',
                        'a push notification service, which delivers alerts to your device.',
                    ]],
                    ['type' => 'p', 'text' => 'And when the law requires it, or to protect someone’s safety.'],
                ],
            ],
            [
                'heading' => 'How long we keep it',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Your account and content are kept while your account exists. When you delete your account we remove it from the live service, and it clears from our backups within :backup_days days. Server logs are kept for a short period and then discarded.'],
                ],
            ],
            [
                'heading' => 'Your choices',
                'blocks' => [
                    ['type' => 'ul', 'items' => [
                        'See and correct: your profile is editable in the app at any time.',
                        'Delete: you can delete individual wins, or your whole account, from inside the app.',
                        'Notifications: push notifications can be turned off in your device settings, and permission withdrawn at any time.',
                        'Ask us: depending on where you live you may have rights to a copy of your data, to correct it, to have it erased, or to object to how it is used. Write to us and we will help.',
                    ]],
                ],
            ],
            [
                'heading' => 'Security',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Traffic is encrypted in transit. Passwords are hashed, never stored as text, and reset codes are stored only as hashes and expire quickly. No system is perfectly secure, but we take this seriously and will tell you promptly if something goes wrong that affects you.'],
                ],
            ],
            [
                'heading' => 'Children',
                'blocks' => [
                    ['type' => 'p', 'text' => ':app is not for children under :age. We do not knowingly collect information from them. If you believe a child has given us their information, write to us and we will delete it.'],
                ],
            ],
            [
                'heading' => 'Where your data is held',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Your information may be stored and processed in countries other than your own, on servers run by the providers listed above. Where we transfer it, we take steps to see it stays protected.'],
                ],
            ],
            [
                'heading' => 'Changes',
                'blocks' => [
                    ['type' => 'p', 'text' => 'If we change this policy in a way that matters, we will tell you in the app or by email before it takes effect. The date at the top tells you which version you are reading.'],
                ],
            ],
            [
                'heading' => 'Reaching us',
                'blocks' => [
                    ['type' => 'p', 'text' => 'Questions about privacy, or a request about your data: :email.'],
                ],
            ],
        ],
    ],

];
