<?php

namespace App\Enums;

enum RowLayout: string
{
    case ONE_COLUMN = '4_4';
    case TWO_COLUMNS_EQUAL = '1_2,1_2';
    case THREE_COLUMNS_EQUAL = '1_3,1_3,1_3';
    case FOUR_COLUMNS_EQUAL = '1_4,1_4,1_4,1_4';
    case FIVE_COLUMNS_EQUAL = '1_5,1_5,1_5,1_5,1_5';
    case SIX_COLUMNS_EQUAL = '1_6,1_6,1_6,1_6,1_6,1_6';
    case TWO_FIFTHS_THREE_FIFTHS = '2_5,3_5';
    case THREE_FIFTHS_TWO_FIFTHS = '3_5,2_5';
    case ONE_THIRD_TWO_THIRDS = '1_3,2_3';
    case TWO_THIRDS_ONE_THIRD = '2_3,1_3';
    case ONE_FOURTH_THREE_FOURTHS = '1_4,3_4';
    case THREE_FOURTHS_ONE_FOURTH = '3_4,1_4';
    case ONE_FOURTH_HALF_ONE_FOURTH = '1_4,1_2,1_4';
    case ONE_FIFTH_THREE_FIFTHS_ONE_FIFTH = '1_5,3_5,1_5';
    case ONE_FOURTH_ONE_FOURTH_HALF = '1_4,1_4,1_2';
    case HALF_ONE_FOURTH_ONE_FOURTH = '1_2,1_4,1_4';
    case ONE_FIFTH_ONE_FIFTH_THREE_FIFTHS = '1_5,1_5,3_5';
    case THREE_FIFTHS_ONE_FIFTH_ONE_FIFTH = '3_5,1_5,1_5';
    case ONE_SIXTH_ONE_SIXTH_ONE_SIXTH_HALF = '1_6,1_6,1_6,1_2';
    case HALF_ONE_SIXTH_ONE_SIXTH_ONE_SIXTH = '1_2,1_6,1_6,1_6';
}
