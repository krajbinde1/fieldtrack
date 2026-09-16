<?php

namespace App\Support;

final class MaharashtraGeoCatalog
{
    /**
     * Complete Maharashtra district → taluka master used by seeders.
     *
     * @return list<array{code: string, name: string, talukas: list<array{code: string, name: string}>}>
     */
    public static function districts(): array
    {
        return [
            ['code' => 'AHMEDNAGAR', 'name' => 'Ahmednagar', 'talukas' => self::named(
                'Akole', 'Jamkhed', 'Karjat', 'Kopargaon', 'Nagar', 'Nevasa', 'Parner', 'Pathardi', 'Rahata', 'Rahuri', 'Sangamner', 'Shevgaon', 'Shrigonda', 'Shrirampur',
            )],
            ['code' => 'AKOLA', 'name' => 'Akola', 'talukas' => self::named(
                'Akola', 'Akot', 'Balapur', 'Barshitakli', 'Murtizapur', 'Patur', 'Telhara',
            )],
            ['code' => 'AMRAVATI', 'name' => 'Amravati', 'talukas' => self::named(
                'Achalpur', 'Amravati', 'Anjangaon Surji', 'Bhatkuli', 'Chandur Bazar', 'Chandur Railway', 'Chikhaldara', 'Daryapur', 'Dhamangaon Railway', 'Dharni', 'Morshi', 'Nandgaon-Khandeshwar', 'Teosa', 'Warud',
            )],
            ['code' => 'CSN', 'name' => 'Chhatrapati Sambhajinagar', 'talukas' => self::named(
                'Chhatrapati Sambhajinagar', 'Gangapur', 'Kannad', 'Khuldabad', 'Paithan', 'Phulambri', 'Sillod', 'Soegaon', 'Vaijapur',
            )],
            ['code' => 'BEED', 'name' => 'Beed', 'talukas' => self::named(
                'Ambajogai', 'Ashti', 'Beed', 'Dharur', 'Georai', 'Kaij', 'Majalgaon', 'Parli', 'Patoda', 'Shirur Kasar', 'Wadwani',
            )],
            ['code' => 'BHANDARA', 'name' => 'Bhandara', 'talukas' => self::named(
                'Bhandara', 'Lakhandur', 'Lakhani', 'Mohadi', 'Pauni', 'Sakoli', 'Tumsar',
            )],
            ['code' => 'BULDHANA', 'name' => 'Buldhana', 'talukas' => self::named(
                'Buldhana', 'Chikhli', 'Deulgaon Raja', 'Jalgaon Jamod', 'Khamgaon', 'Lonar', 'Malkapur', 'Mehkar', 'Motala', 'Nandura', 'Sangrampur', 'Shegaon', 'Sindkhed Raja',
            )],
            ['code' => 'CHANDRAPUR', 'name' => 'Chandrapur', 'talukas' => self::named(
                'Ballarpur', 'Bhadravati', 'Brahmapuri', 'Chandrapur', 'Chimur', 'Gondpipri', 'Jiwati', 'Korpana', 'Mul', 'Nagbhid', 'Pombhurna', 'Rajura', 'Saoli', 'Sindewahi', 'Warora',
            )],
            ['code' => 'DHULE', 'name' => 'Dhule', 'talukas' => self::named(
                'Dhule', 'Sakri', 'Shindkheda', 'Shirpur',
            )],
            ['code' => 'GADCHIROLI', 'name' => 'Gadchiroli', 'talukas' => self::named(
                'Aheri', 'Armori', 'Bhamragad', 'Chamorshi', 'Desaiganj', 'Dhanora', 'Etapalli', 'Gadchiroli', 'Korchi', 'Kurkheda', 'Mulchera', 'Sironcha',
            )],
            ['code' => 'GONDIA', 'name' => 'Gondia', 'talukas' => self::named(
                'Amgaon', 'Arjuni Morgaon', 'Deori', 'Gondia', 'Goregaon', 'Sadak Arjuni', 'Salekasa', 'Tirora',
            )],
            ['code' => 'HINGOLI', 'name' => 'Hingoli', 'talukas' => self::named(
                'Aundha Nagnath', 'Basmath', 'Hingoli', 'Kalamnuri', 'Sengaon',
            )],
            ['code' => 'JALGAON', 'name' => 'Jalgaon', 'talukas' => self::named(
                'Amalner', 'Bhadgaon', 'Bhusawal', 'Bodwad', 'Chalisgaon', 'Chopda', 'Dharangaon', 'Erandol', 'Jalgaon', 'Jamner', 'Muktainagar', 'Pachora', 'Parola', 'Raver', 'Yawal',
            )],
            ['code' => 'JALNA', 'name' => 'Jalna', 'talukas' => self::named(
                'Ambad', 'Badnapur', 'Bhokardan', 'Ghansawangi', 'Jafferabad', 'Jalna', 'Mantha', 'Partur',
            )],
            ['code' => 'KOLHAPUR', 'name' => 'Kolhapur', 'talukas' => self::named(
                'Ajara', 'Bavda', 'Chandgad', 'Gadhinglaj', 'Gaganbawada', 'Hatkanangale', 'Kagal', 'Karvir', 'Panhala', 'Radhanagari', 'Shahuwadi', 'Shirol',
            )],
            ['code' => 'LATUR', 'name' => 'Latur', 'talukas' => self::named(
                'Ahmadpur', 'Ausa', 'Chakur', 'Deoni', 'Jalkot', 'Latur', 'Nilanga', 'Renapur', 'Shirur Anantpal', 'Udgir',
            )],
            ['code' => 'MUMBAI_CITY', 'name' => 'Mumbai City', 'talukas' => self::named(
                'Mumbai',
            )],
            ['code' => 'MUMBAI_SUBURBAN', 'name' => 'Mumbai Suburban', 'talukas' => self::named(
                'Andheri', 'Borivali', 'Kurla',
            )],
            ['code' => 'NAGPUR', 'name' => 'Nagpur', 'talukas' => self::named(
                'Bhiwapur', 'Hingna', 'Kalameshwar', 'Kamptee', 'Katol', 'Kuhi', 'Mauda', 'Nagpur Rural', 'Nagpur Urban', 'Narkhed', 'Parseoni', 'Ramtek', 'Savner', 'Umred',
            )],
            ['code' => 'NANDED', 'name' => 'Nanded', 'talukas' => self::named(
                'Ardhapur', 'Bhokar', 'Biloli', 'Deglur', 'Dharmabad', 'Hadgaon', 'Himayatnagar', 'Kandhar', 'Kinwat', 'Loha', 'Mahur', 'Mudkhed', 'Mukhed', 'Naigaon', 'Nanded', 'Umri',
            )],
            ['code' => 'NANDURBAR', 'name' => 'Nandurbar', 'talukas' => self::named(
                'Akkalkuwa', 'Akrani', 'Nandurbar', 'Navapur', 'Shahada', 'Taloda',
            )],
            ['code' => 'NASHIK', 'name' => 'Nashik', 'talukas' => self::named(
                'Baglan', 'Chandwad', 'Deola', 'Dindori', 'Igatpuri', 'Kalwan', 'Malegaon', 'Nandgaon', 'Nashik', 'Niphad', 'Peint', 'Sinnar', 'Surgana', 'Trimbakeshwar', 'Yevla',
            )],
            ['code' => 'DHARASHIV', 'name' => 'Dharashiv', 'talukas' => self::named(
                'Bhum', 'Kalamb', 'Lohara', 'Dharashiv', 'Paranda', 'Tuljapur', 'Umarga', 'Washi',
            )],
            ['code' => 'PALGHAR', 'name' => 'Palghar', 'talukas' => self::named(
                'Dahanu', 'Jawhar', 'Mokhada', 'Palghar', 'Talasari', 'Vasai', 'Vikramgad', 'Wada',
            )],
            ['code' => 'PARBHANI', 'name' => 'Parbhani', 'talukas' => self::named(
                'Gangakhed', 'Jintur', 'Manwat', 'Palam', 'Parbhani', 'Pathri', 'Purna', 'Sailu', 'Sonpeth',
            )],
            ['code' => 'PUNE', 'name' => 'Pune', 'talukas' => self::named(
                'Ambegaon', 'Baramati', 'Bhor', 'Daund', 'Haveli', 'Indapur', 'Junnar', 'Khed', 'Maval', 'Mulshi', 'Pune City', 'Purandar', 'Shirur', 'Velhe',
            )],
            ['code' => 'RAIGAD', 'name' => 'Raigad', 'talukas' => self::named(
                'Alibag', 'Karjat', 'Khalapur', 'Mahad', 'Mangaon', 'Mhasla', 'Murud', 'Panvel', 'Pen', 'Poladpur', 'Roha', 'Shrivardhan', 'Sudhagad', 'Tala', 'Uran',
            )],
            ['code' => 'RATNAGIRI', 'name' => 'Ratnagiri', 'talukas' => self::named(
                'Chiplun', 'Dapoli', 'Guhagar', 'Khed', 'Lanja', 'Mandangad', 'Rajapur', 'Ratnagiri', 'Sangameshwar',
            )],
            ['code' => 'SANGLI', 'name' => 'Sangli', 'talukas' => self::named(
                'Atpadi', 'Jat', 'Kadegaon', 'Kavathe Mahankal', 'Khanapur', 'Miraj', 'Palus', 'Shirala', 'Tasgaon', 'Walwa',
            )],
            ['code' => 'SATARA', 'name' => 'Satara', 'talukas' => self::named(
                'Jaoli', 'Karad', 'Khandala', 'Khatav', 'Koregaon', 'Mahabaleshwar', 'Man', 'Patan', 'Phaltan', 'Satara', 'Wai',
            )],
            ['code' => 'SINDHUDURG', 'name' => 'Sindhudurg', 'talukas' => self::named(
                'Devgad', 'Dodamarg', 'Kankavli', 'Kudal', 'Malvan', 'Sawantwadi', 'Vaibhavwadi', 'Vengurla',
            )],
            ['code' => 'SOLAPUR', 'name' => 'Solapur', 'talukas' => self::named(
                'Akkalkot', 'Barshi', 'Karmala', 'Madha', 'Malshiras', 'Mangalvedhe', 'Mohol', 'Pandharpur', 'Sangole', 'Solapur North', 'Solapur South',
            )],
            ['code' => 'THANE', 'name' => 'Thane', 'talukas' => self::named(
                'Ambarnath', 'Bhiwandi', 'Kalyan', 'Murbad', 'Shahapur', 'Thane', 'Ulhasnagar',
            )],
            ['code' => 'WARDHA', 'name' => 'Wardha', 'talukas' => self::named(
                'Arvi', 'Ashti', 'Deoli', 'Hinganghat', 'Karanja', 'Samudrapur', 'Seloo', 'Wardha',
            )],
            ['code' => 'WASHIM', 'name' => 'Washim', 'talukas' => self::named(
                'Karanja', 'Malegaon', 'Mangrulpir', 'Manora', 'Risod', 'Washim',
            )],
            ['code' => 'YAVATMAL', 'name' => 'Yavatmal', 'talukas' => self::named(
                'Arni', 'Babulgaon', 'Darwha', 'Digras', 'Ghatanji', 'Kalamb', 'Kelapur', 'Mahagaon', 'Maregaon', 'Ner', 'Pusad', 'Ralegaon', 'Umarkhed', 'Wani', 'Yavatmal', 'Zari Jamani',
            )],
        ];
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    private static function named(string ...$names): array
    {
        $talukas = [];

        foreach ($names as $index => $name) {
            $talukas[] = [
                'code' => strtoupper(str_replace([' ', '-', '/', '(', ')'], ['_', '_', '_', '', ''], $name)),
                'name' => $name,
                'sort' => $index + 1,
            ];
        }

        return $talukas;
    }

    public static function districtCount(): int
    {
        return count(self::districts());
    }

    public static function talukaCount(): int
    {
        $count = 0;
        foreach (self::districts() as $district) {
            $count += count($district['talukas']);
        }

        return $count;
    }
}
