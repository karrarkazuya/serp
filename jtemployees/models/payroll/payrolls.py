from odoo import models, fields, exceptions
from datetime import datetime, timedelta

class payrolls(models.Model):
    _name = 'jtemployees.payrolls'
    _description = 'Payroll'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name                   = fields.Text(string='Title', tracking=True)
    
    employee_ids           = fields.Many2many('hr.employee', string='Employees', required=True, tracking=True)
    
    date_selected_from     = fields.Date(string='Date From', tracking=True)
    date_selected_to       = fields.Date(string='Date To', tracking=True)
    month_selected         = fields.Selection([("january", '(1) January'), ("february", '(2) February'), ("march", '(3) March'), ("april", '(4) April'),
                            ("may", '(5) May'), ("june", '(6) June'), ("july", '(7) July'), ("august", '(8) August'), 
                            ("september", '(9) September'), ("october", '(10) October'), ("november", '(11) November'), ("december", '(12) December'), ], tracking=True)
    is_monthly             = fields.Boolean(string='Is monthly payroll', default=True)
    total_amount           = fields.Float(string='Total amount', compute='_compute_total_amount')
    payrolls               = fields.One2many('jtemployees.payrolls.slips', inverse_name="payroll", string='Slips', domain=[('deleted', '=', False)])
    state                  = fields.Selection([("pending", 'Pending'), ('accountent_review', 'Accountant Review'), ("approved", 'Approved')], string='Payroll state', default='pending', tracking=True)
    
    shortages_ignored             = fields.Boolean(string='Ignore all shortages', help='when selected, no shortages will be calcualted for employee, requests will still be processed.', default=False, tracking=True)
    shortages_ignored_from        = fields.Date(string='Ignore shorages from', help='Period for ignored shortages', tracking=True)
    shortages_ignored_to          = fields.Date(string='Ignore shorages to', help='Period for ignored shortages', tracking=True)
    
    match_period_amount           = fields.Boolean(string='Force match period salary', help='when selected, payroll slips amounts shall be matched to the period. resulting in different amounts if the period is different than 30 days', default=False, tracking=True)

    default_working_schedule      = fields.Many2one('resource.calendar', string='Fallover Working schedule', help="This working schedule for in case some dont have working schedule and want to just pass the payroll", tracking=True)


    deleted                = fields.Boolean(string='Active', default=False, tracking=True)


    def create(self, vals):
        if "is_monthly" in vals and vals['is_monthly']:
            if 'month_selected' not in vals or not vals['month_selected'] or vals['month_selected']  == '':
                raise exceptions.ValidationError("Month date is required.")
            
            current_datetime = datetime.now()
            current_year = current_datetime.year
            month_number = datetime.strptime(vals['month_selected'], "%B").month
            date_from = datetime.strptime(str(current_year) + "-" + str(month_number) + "-01", '%Y-%m-%d')
            month_number += 1
            if month_number == 13:
                month_number = 1
                current_year = current_year + 1
            date_to = datetime.strptime(str(current_year) + "-" + str(month_number) + "-01", '%Y-%m-%d') - timedelta(days=1)
            
            vals['date_selected_from'] = date_from
            vals['date_selected_to'] = date_to
            date_from = date_from.strftime("%Y-%m-%d")
            date_to   = date_to.strftime("%Y-%m-%d")
        else:
            if 'date_selected_from' not in vals or 'date_selected_to' not in vals:
                raise exceptions.ValidationError("Month date is required.")
            
            date_from = vals['date_selected_from']
            date_to = vals['date_selected_to']
            
            if date_from > date_to:
                raise exceptions.ValidationError("Month date is not correct.")
            
            
        payroll = super(payrolls, self).create(vals)
        
        for employee in payroll.employee_ids:
            self.env['jtemployees.payrolls.slips'].create([{
                "employee": employee.id,
                "payroll": payroll.id,
                "date_selected_from": date_from,
                "date_selected_to": date_to,
                "shortages_ignored": payroll.shortages_ignored,
                "shortages_ignored_from": payroll.shortages_ignored_from,
                "shortages_ignored_to": payroll.shortages_ignored_to,
                "match_period_amount": payroll.match_period_amount,
                "default_working_schedule": payroll.default_working_schedule.id
            }])
        
        return payroll
    
    def write(self, values):
        old_state = self.state
        if "deleted" in values:
            return super().write(values)
        if old_state == "approved":
            raise exceptions.ValidationError("You are not allowed to modify an approved.")
        if old_state == "pending" or old_state == "accountent_review":
            # prevent changing state and data at the same time
            if 'state' in values and len(values) > 1:
                raise exceptions.ValidationError("Make changes and submit first and then change the state.")
            
            if 'state' in values:
                if values['state'] == "approved":
                    if self.env.user.has_group('jtemployees.group_accountant'):
                        if old_state == "accountent_review":
                            payroll_slips = self.env['jtemployees.payrolls.slips'].sudo().search([('payroll', '=', self.id), ('deleted', '=', False), ('approved', '=', False)])
                            for payroll_slip in payroll_slips:
                                payroll_slip.approved = True
                        else:
                            raise exceptions.ValidationError("The payroll must be submitted to accountents first")
                    else:
                        raise exceptions.ValidationError("Only accountant can approve payroll")
                       
                return super().write({"state": values['state']})
                
            if old_state == "accountent_review":
                raise exceptions.ValidationError("Changes can not be made when payroll is in Accountant Review")
            if not ((self.env.user.has_group('jtemployees.group_admin') or self.env.user.has_group('jtemployees.group_hr_admin') or self.env.user.has_group('jtemployees.group_hr_manager'))):
                    raise exceptions.ValidationError("You are not allowed to modify this field")
                
            res = super().write(values)
            
            if old_state == "pending":
                if 'state' not in values:
                    # we delete old payrolls
                    for payroll_slip in self.payrolls:
                        payroll_slip.unlink()
                    employees = self.employee_ids
                    for employee in employees:
                        self.env['jtemployees.payrolls.slips'].create([{
                            "employee": employee.id,
                            "payroll": self.id,
                            "date_selected_from": self.date_selected_from.strftime('%Y-%m-%d'),
                            "date_selected_to": self.date_selected_to.strftime('%Y-%m-%d'),
                            "shortages_ignored": self.shortages_ignored,
                            "shortages_ignored_from": self.shortages_ignored_from,
                            "shortages_ignored_to": self.shortages_ignored_to,
                            "match_period_amount": self.match_period_amount
                        }])
            
        return res
    
    def unlink(self):
        for record in self:
            slips = self.env['jtemployees.payrolls.slips'].search([("payroll", "=", record.id)])
            for slip in slips:
                slip.write({'deleted': True})
            record.sudo().write({'deleted': True})
        return True


    def _compute_total_amount(self):
        for record in self:
            total_amount = 0.0
            slips = self.env['jtemployees.payrolls.slips'].search([("payroll", "=", record.id)])
            for slip in slips:
                details = self.env['jtemployees.payrolls.details'].search([("payroll_slip", "=", slip.id)])
                for detail in details:
                    total_amount += detail.amount
                    
            if total_amount < 0:
                total_amount = 0
            record.total_amount = total_amount
            

