<?php

namespace App\Http\Controllers;

use App\Mail\Winner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PromotionsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Stores promotion details.
     * This will check if client exists (will create if it does not).
     * @param $slug
     * @param Request $request
     * @return JsonResponse
     */
    public function save($slug, Request $request)
    {

        // define the data
        $data           = $request->all();
        $promoName      = $data['promo_name'] ?? false;
        $winningMoment  = $data['winning_moment'] ?? false;
        $chance         = $data['chance'] ?? false;

        // validate the data
        if ($promoName === false)
        {
            return response()->json('Value for promo_name is not defined.', 400);
        }
        if ($winningMoment === false)
        {
            return response()->json('Value for winning_moment is not defined.', 400);
        }
        if ($chance === false)
        {
            return response()->json('Value for chance is not defined.', 400);
        }

        // Check if slug exists in client table
        $client = DB::table('clients')->where('client_slug', $slug)->get();
        $clientId = 0;

        // If yes, get ID
        if (count($client) > 0) {
            $clientId = $client[0]->id;
        }
        // If no, create and get ID
        else {
            $newClient = DB::table('clients')->insertGetId(['client_slug' => $slug]);
            $clientId = $newClient;
        }

        // Create promotion and get ID
        $promotion = DB::table('promotions')->insertGetId([
            'client_id'         => $clientId,
            'promotion_name'    => $promoName
        ]);

        // Create mechanics and get ID
        DB::table('mechanics')->insert([
            'promotion_id'      => $promotion,
            'winning_moment'    => $winningMoment,
            'chance'            => $chance
        ]);

        // Return the client, promotion, and mechanic details
        return response()->json([
            'client'            => $slug,
            'promotion_name'    => $promoName,
            'winning_moment'    => $winningMoment,
            'chance'            => $chance
        ]);
    }

    /**
     * Stores entrant details and
     * checks if winning moment value matches the mechanics.
     * Will inform entrant via email if he/she wins.
     * @param Request $request
     * @return JsonResponse
     */
    public function checkWinningMoment(Request $request)
    {

        // define the data
        $data           = $request->all();
        $entrantName    = $data['entrant_name'] ?? false;
        $entrantEmail   = $data['entrant_email'] ?? false;
        $promoName      = $data['promo_name'] ?? false;
        $winningMoment  = Carbon::parse($data['winning_moment'])->format('Y-m-d H:i:s') ?? false;
        $isWinner       = false;

        // validate the data
        if ($entrantName === false)
        {
            return response()->json('Value for entrant_name is not defined.', 400);
        }
        if ($entrantEmail === false)
        {
            return response()->json('Value for entrant_email is not defined.', 400);
        }
        if ($promoName === false)
        {
            return response()->json('Value for promo_name is not defined.', 400);
        }
        if ($winningMoment === false)
        {
            return response()->json('Value for winning_moment is not defined.', 400);
        }

        // Check if entrant exists in client table
        $entrants = DB::table('entrants')->where('entrant_email', $entrantEmail)->get();
        $entrantId = 0;

        // If yes, get ID
        if (count($entrants) > 0) {
            $entrantId = $entrants[0]->id;
        }
        // If no, create and get ID
        else {
            $newEntrant = DB::table('entrants')->insertGetId([
                'entrant_name'  => $entrantName,
                'entrant_email' => $entrantEmail
            ]);

            $entrantId = $newEntrant;
        }

        // Check if promo exists
        $promotions = DB::table('promotions')->where('promotion_name', $promoName)->get();
        $promotionId = 0;

        if (count($promotions) > 0) {
            $promotionId = $promotions[0]->id;
        }
        else {
            return response()->json('The promotion name ' . $promoName . ' does not exist.', 404);
        }

        // add entrant to entries, for record purposes
        $newEntryId = DB::table('entries')->insertGetId([
            'promotion_id'      => $promotionId,
            'entrant_id'        => $entrantId,
            'winning_moment'    => $winningMoment
        ]);

        // check if there exists a promotion with winning moment
        $mechanicDetails = DB::table('mechanics')->where([
            'promotion_id'      => $promotionId,
            'winning_moment'    => $winningMoment
        ])->get();

        if (count($mechanicDetails)) {
            DB::table('winners')->insertGetId([
                'entry_id' => $newEntryId
            ]);

            $isWinner = true;

            // TODO: send email
            Mail::to($entrantEmail)->send(new Winner());
        }

        // Return the entrant name, promotion name, winning moment, and if he won or not
        return response()->json([
            'entrant_name'      => $entrantName,
            'promotion_name'    => $promoName,
            'winning_moment'    => $winningMoment,
            'is_winner'         => $isWinner
        ]);
    }

    /**
     * Stores entrant details and
     * checks if chance value matches the mechanics.
     * Will inform entrant via email if he/she wins.
     * TODO needs to refactor code since both checkChance and checkWinningMoment has similar process logic
     * @param Request $request
     * @return JsonResponse
     */
    public function checkChance(Request $request)
    {

        // define the data
        $data           = $request->all();
        $entrantName    = $data['entrant_name'] ?? false;
        $entrantEmail   = $data['entrant_email'] ?? false;
        $promoName      = $data['promo_name'] ?? false;
        $chance         = $data['chance'] ?? false;
        $isWinner       = false;

        // validate the data
        if ($entrantName === false)
        {
            return response()->json('Value for entrant_name is not defined.', 400);
        }
        if ($entrantEmail === false)
        {
            return response()->json('Value for entrant_email is not defined.', 400);
        }
        if ($promoName === false)
        {
            return response()->json('Value for promo_name is not defined.', 400);
        }
        if ($chance === false)
        {
            return response()->json('Value for chance is not defined.', 400);
        }

        // Check if entrant exists in client table
        $entrants = DB::table('entrants')->where('entrant_email', $entrantEmail)->get();
        $entrantId = 0;

        // If yes, get ID
        if (count($entrants) > 0) {
            $entrantId = $entrants[0]->id;
        }
        // If no, create and get ID
        else {
            $newEntrant = DB::table('entrants')->insertGetId([
                'entrant_name'  => $entrantName,
                'entrant_email' => $entrantEmail
            ]);

            $entrantId = $newEntrant;
        }

        // Check if promo exists
        $promotions = DB::table('promotions')->where('promotion_name', $promoName)->get();
        $promotionId = 0;

        if (count($promotions) > 0) {
            $promotionId = $promotions[0]->id;
        }
        else {
            return response()->json('The promotion name ' . $promoName . ' does not exist.', 404);
        }

        // add entrant to entries, for record purposes
        $newEntryId = DB::table('entries')->insertGetId([
            'promotion_id'  => $promotionId,
            'entrant_id'    => $entrantId,
            'chance'        => $chance
        ]);

        // check if there exists a promotion with chance
        $mechanicDetails = DB::table('mechanics')->where([
            'promotion_id'  => $promotionId,
            'chance'        => $chance
        ])->get();

        if (count($mechanicDetails)) {
            DB::table('winners')->insertGetId([
                'entry_id' => $newEntryId
            ]);

            $isWinner = true;

            // TODO: send email
            Mail::to($entrantEmail)->send(new Winner());
        }

        // Return the entrant name, promotion name, winning moment, and if he won or not
        return response()->json([
            'entrant_name'      => $entrantName,
            'promotion_name'    => $promoName,
            'chance'            => $chance,
            'is_winner'         => $isWinner
        ]);
    }
}
