<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1a1a1a;
            padding: 40px;
            line-height: 1.6;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #1a3c6e;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        .header .university {
            font-size: 15px;
            font-weight: bold;
            color: #1a3c6e;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .header .ministry {
            font-size: 11px;
            color: #555;
            margin-bottom: 8px;
        }

        .header .doc-title {
            font-size: 20px;
            font-weight: bold;
            color: #1a3c6e;
            margin-top: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .header .convention-number {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }

        .section {
            margin-bottom: 20px;
        }

        .section-title {
            background-color: #1a3c6e;
            color: white;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        table td {
            padding: 7px 10px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        table td.label {
            background-color: #f0f4fb;
            font-weight: bold;
            width: 40%;
            color: #1a3c6e;
        }

        .article {
            margin-bottom: 12px;
        }

        .article-title {
            font-weight: bold;
            color: #1a3c6e;
            margin-bottom: 4px;
        }

        .article-body {
            text-align: justify;
            color: #333;
        }

        .skills-list {
            display: inline;
        }

        .skill-tag {
            background: #e8f0fe;
            color: #1a3c6e;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            margin-right: 4px;
        }

        .signatures {
            margin-top: 40px;
            width: 100%;
        }

        .signatures table td {
            border: none;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }

        .sig-box {
            border-top: 1px solid #333;
            padding-top: 8px;
            margin-top: 60px;
            font-size: 11px;
            color: #555;
        }

        .footer {
            position: fixed;
            bottom: 20px;
            left: 40px;
            right: 40px;
            border-top: 1px solid #ccc;
            padding-top: 8px;
            font-size: 10px;
            color: #999;
            text-align: center;
        }

        .stamp-area {
            border: 2px dashed #ccc;
            height: 80px;
            margin-top: 20px;
            text-align: center;
            line-height: 80px;
            color: #bbb;
            font-size: 11px;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="ministry">République Algérienne Démocratique et Populaire</div>
        <div class="ministry">Ministère de l'Enseignement Supérieur et de la Recherche Scientifique</div>
        <div class="university">{{ $university_name }}</div>
        <div class="doc-title">Convention de Stage</div>
        <div class="convention-number">N° {{ $convention_number }} — {{ \Carbon\Carbon::now()->format('Y') }}</div>
    </div>

    <div class="section">
        <p style="text-align:justify; margin-bottom:12px;">
            La présente convention est conclue entre l'établissement d'enseignement supérieur,
            l'entreprise d'accueil et l'étudiant(e) stagiaire, conformément à la réglementation
            en vigueur relative aux stages en milieu professionnel.
        </p>
    </div>

    <div class="section">
        <div class="section-title">Article 1 — Établissement d'Enseignement</div>
        <table>
            <tr>
                <td class="label">Établissement</td>
                <td>{{ $university_name }}</td>
            </tr>
            <tr>
                <td class="label">Département</td>
                <td>{{ $department }}</td>
            </tr>
            <tr>
                <td class="label">Responsable pédagogique</td>
                <td>{{ $department_head }}</td>
            </tr>
            <tr>
                <td class="label">Adresse</td>
                <td>{{ $university_address }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Article 2 — Étudiant(e) Stagiaire</div>
        <table>
            <tr>
                <td class="label">Nom complet</td>
                <td>{{ $student->full_name }}</td>
            </tr>
            <tr>
                <td class="label">Email</td>
                <td>{{ $student->email }}</td>
            </tr>
            <tr>
                <td class="label">Téléphone</td>
                <td>{{ $student->phone ?? 'Non renseigné' }}</td>
            </tr>
            <tr>
                <td class="label">Wilaya</td>
                <td>{{ $student->wilaya ?? 'Non renseigné' }}</td>
            </tr>
            <tr>
                <td class="label">Université</td>
                <td>{{ $student->university ?? 'Non renseigné' }}</td>
            </tr>
            <tr>
                <td class="label">Filière</td>
                <td>{{ $student->field_of_study ?? 'Non renseigné' }}</td>
            </tr>
            <tr>
                <td class="label">Niveau académique</td>
                <td>{{ $student->academic_level ?? 'Non renseigné' }}</td>
            </tr>
            @if($student->skills && count($student->skills) > 0)
            <tr>
                <td class="label">Compétences techniques</td>
                <td>
                    @foreach($student->skills as $skill)
                        <span class="skill-tag">{{ $skill }}</span>
                    @endforeach
                </td>
            </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">Article 3 — Entreprise d'Accueil</div>
        <table>
            <tr>
                <td class="label">Raison sociale</td>
                <td>{{ $company->company_name }}</td>
            </tr>
            <tr>
                <td class="label">Secteur d'activité</td>
                <td>{{ $company->industry ?? 'Non renseigné' }}</td>
            </tr>
            <tr>
                <td class="label">Localisation</td>
                <td>{{ $company->location ?? 'Non renseigné' }}</td>
            </tr>
            <tr>
                <td class="label">Site web</td>
                <td>{{ $company->website_url ?? 'Non renseigné' }}</td>
            </tr>
            <tr>
                <td class="label">Taille</td>
                <td>{{ $company->company_size ?? 'Non renseigné' }} employés</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Article 4 — Objet du Stage</div>
        <table>
            <tr>
                <td class="label">Intitulé du poste</td>
                <td>{{ $offer->title }}</td>
            </tr>
            <tr>
                <td class="label">Domaine</td>
                <td>{{ $offer->domain }}</td>
            </tr>
            <tr>
                <td class="label">Type de stage</td>
                <td>{{ ucfirst($offer->type) }}</td>
            </tr>
            <tr>
                <td class="label">Durée</td>
                <td>{{ $offer->duration_value }} {{ $offer->duration_unit === 'months' ? 'mois' : 'semaines' }}</td>
            </tr>
            <tr>
                <td class="label">Date de génération</td>
                <td>{{ \Carbon\Carbon::now()->format('d/m/Y') }}</td>
            </tr>
            @if($offer->required_skills && count($offer->required_skills) > 0)
            <tr>
                <td class="label">Compétences requises</td>
                <td>
                    @foreach($offer->required_skills as $skill)
                        <span class="skill-tag">{{ $skill }}</span>
                    @endforeach
                </td>
            </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">Article 5 — Obligations des Parties</div>

        <div class="article">
            <div class="article-title">5.1 — L'établissement s'engage à :</div>
            <div class="article-body">
                Assurer le suivi pédagogique de l'étudiant durant toute la période de stage,
                désigner un enseignant tuteur, et valider le rapport de stage dans les délais impartis.
            </div>
        </div>

        <div class="article">
            <div class="article-title">5.2 — L'entreprise s'engage à :</div>
            <div class="article-body">
                Accueillir le stagiaire dans de bonnes conditions, lui confier des missions
                en rapport avec sa formation, désigner un maître de stage, et lui fournir
                les moyens nécessaires à l'accomplissement de ses tâches.
            </div>
        </div>

        <div class="article">
            <div class="article-title">5.3 — L'étudiant(e) s'engage à :</div>
            <div class="article-body">
                Respecter le règlement intérieur de l'entreprise, observer la confidentialité
                des informations auxquelles il/elle aura accès, et remettre un rapport de stage
                à l'issue de la période de stage.
            </div>
        </div>
    </div>

    <div class="section signatures">
        <div class="section-title">Signatures</div>
        <table>
            <tr>
                <td style="width:33%">
                    <strong>Le Responsable Pédagogique</strong>
                    <div style="font-size:11px; color:#555;">{{ $university_name }}</div>
                    <div class="sig-box">Signature & Cachet</div>
                </td>
                <td style="width:33%">
                    <strong>Le Maître de Stage</strong>
                    <div style="font-size:11px; color:#555;">{{ $company->company_name }}</div>
                    <div class="sig-box">Signature & Cachet</div>
                </td>
                <td style="width:33%">
                    <strong>L'Étudiant(e)</strong>
                    <div style="font-size:11px; color:#555;">{{ $student->full_name }}</div>
                    <div class="sig-box">Signature</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Convention de Stage N° {{ $convention_number }} —
        Généré automatiquement par Stag.io le {{ \Carbon\Carbon::now()->format('d/m/Y à H:i') }} —
        {{ $university_name }}
    </div>

</body>
</html>

